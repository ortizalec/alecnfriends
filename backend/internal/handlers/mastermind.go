package handlers

import (
	"encoding/json"
	"net/http"

	"altech/internal/db"
	"altech/internal/mastermind"
	"altech/internal/middleware"
	"altech/internal/models"
)

func (h *Handler) GetMastermindGames(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	games, err := db.GetMastermindGamesForUser(h.db, userCtx.UserID)
	if err != nil {
		jsonError(w, "failed to get games", http.StatusInternalServerError)
		return
	}

	response := models.MastermindGamesListResponse{
		YourTurn:  []models.MastermindGame{},
		TheirTurn: []models.MastermindGame{},
		Completed: []models.MastermindGame{},
	}

	for _, game := range games {
		if game.Status == "completed" {
			response.Completed = append(response.Completed, game)
		} else if game.Status == "setup" {
			// During setup, check if current user has set their secret
			hasSecret, _ := db.HasUserSetSecret(h.db, game.ID, userCtx.UserID)
			if !hasSecret {
				response.YourTurn = append(response.YourTurn, game)
			} else {
				response.TheirTurn = append(response.TheirTurn, game)
			}
		} else if game.CurrentTurn == userCtx.UserID {
			response.YourTurn = append(response.YourTurn, game)
		} else {
			response.TheirTurn = append(response.TheirTurn, game)
		}
	}

	jsonResponse(w, response, http.StatusOK)
}

func (h *Handler) CreateMastermindGame(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	var req models.CreateMastermindGameRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	if req.OpponentID == userCtx.UserID {
		jsonError(w, "cannot play against yourself", http.StatusBadRequest)
		return
	}

	// Verify friendship
	isFriend, err := db.CheckFriendship(h.db, userCtx.UserID, req.OpponentID)
	if err != nil || !isFriend {
		jsonError(w, "can only play with friends", http.StatusForbidden)
		return
	}

	// Validate and set defaults for difficulty options
	numColors := mastermind.ValidateNumColors(req.NumColors)
	allowRepeats := req.AllowRepeats

	// If numColors is 4 and repeats disabled, that's only 4 options for 4 slots - force enable repeats
	// Actually allow it, but keep the validation

	game, err := db.CreateMastermindGame(h.db, userCtx.UserID, req.OpponentID, numColors, allowRepeats)
	if err != nil {
		jsonError(w, "failed to create game", http.StatusInternalServerError)
		return
	}

	jsonResponse(w, models.MastermindGameResponse{
		Game:         game,
		MyGuesses:    []models.MastermindGuessResponse{},
		TheirGuesses: []models.MastermindGuessResponse{},
		SecretSet:    false,
		IsYourTurn:   true,
		Phase:        "setup",
		Round:        0,
	}, http.StatusCreated)
}

func (h *Handler) GetMastermindGame(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	gameID := extractGameID(r)
	if gameID == 0 {
		jsonError(w, "invalid game ID", http.StatusBadRequest)
		return
	}

	game, err := db.GetMastermindGame(h.db, gameID)
	if err == db.ErrGameNotFound {
		jsonError(w, "game not found", http.StatusNotFound)
		return
	}
	if err != nil {
		jsonError(w, "failed to get game", http.StatusInternalServerError)
		return
	}

	// Check user is a player
	if game.Player1ID != userCtx.UserID && game.Player2ID != userCtx.UserID {
		jsonError(w, "not a player in this game", http.StatusForbidden)
		return
	}

	response := h.buildMastermindResponse(game, userCtx.UserID)
	jsonResponse(w, response, http.StatusOK)
}

func (h *Handler) SetMastermindSecret(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	gameID := extractGameID(r)
	if gameID == 0 {
		jsonError(w, "invalid game ID", http.StatusBadRequest)
		return
	}

	var req models.SetMastermindSecretRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	game, err := db.GetMastermindGame(h.db, gameID)
	if err == db.ErrGameNotFound {
		jsonError(w, "game not found", http.StatusNotFound)
		return
	}
	if err != nil {
		jsonError(w, "failed to get game", http.StatusInternalServerError)
		return
	}

	// Check user is a player
	if game.Player1ID != userCtx.UserID && game.Player2ID != userCtx.UserID {
		jsonError(w, "not a player in this game", http.StatusForbidden)
		return
	}

	if game.Status != "setup" {
		jsonError(w, "game is not in setup phase", http.StatusBadRequest)
		return
	}

	// Validate the code against game settings
	if err := mastermind.ValidateCode(req.Code, game.NumColors, game.AllowRepeats); err != nil {
		jsonError(w, err.Error(), http.StatusBadRequest)
		return
	}

	// Save the secret
	codeJSON, _ := mastermind.CodeToJSON(req.Code)
	if err := db.SetMastermindSecret(h.db, gameID, userCtx.UserID, codeJSON); err != nil {
		jsonError(w, "failed to save secret", http.StatusInternalServerError)
		return
	}

	// Check if both players have set their secrets
	bothSet, _ := db.BothSecretsSet(h.db, gameID)
	if bothSet {
		game.Status = "active"
		db.UpdateMastermindGame(h.db, game)
	}

	// Refetch game for updated status
	game, _ = db.GetMastermindGame(h.db, gameID)

	response := h.buildMastermindResponse(game, userCtx.UserID)
	jsonResponse(w, response, http.StatusOK)
}

func (h *Handler) MakeMastermindGuess(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	gameID := extractGameID(r)
	if gameID == 0 {
		jsonError(w, "invalid game ID", http.StatusBadRequest)
		return
	}

	var req models.MakeMastermindGuessRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	game, err := db.GetMastermindGame(h.db, gameID)
	if err == db.ErrGameNotFound {
		jsonError(w, "game not found", http.StatusNotFound)
		return
	}
	if err != nil {
		jsonError(w, "failed to get game", http.StatusInternalServerError)
		return
	}

	// Check user is a player
	if game.Player1ID != userCtx.UserID && game.Player2ID != userCtx.UserID {
		jsonError(w, "not a player in this game", http.StatusForbidden)
		return
	}

	if game.Status != "active" {
		jsonError(w, "game is not active", http.StatusBadRequest)
		return
	}

	if game.CurrentTurn != userCtx.UserID {
		jsonError(w, "not your turn", http.StatusBadRequest)
		return
	}

	// Validate the guess against game settings
	if err := mastermind.ValidateCode(req.Guess, game.NumColors, game.AllowRepeats); err != nil {
		jsonError(w, err.Error(), http.StatusBadRequest)
		return
	}

	// Get opponent's secret
	opponentID := game.Player1ID
	if userCtx.UserID == game.Player1ID {
		opponentID = game.Player2ID
	}

	opponentSecret, err := db.GetMastermindSecret(h.db, gameID, opponentID)
	if err != nil || opponentSecret == nil {
		jsonError(w, "opponent secret not found", http.StatusInternalServerError)
		return
	}

	secretCode, _ := mastermind.CodeFromJSON(opponentSecret.Code)

	// Evaluate the guess
	correct, misplaced := mastermind.EvaluateGuess(secretCode, req.Guess)

	// Get current guess number
	guessNum, _ := db.GetLatestGuessNumber(h.db, gameID, userCtx.UserID)
	guessNum++

	// Save the guess
	guessJSON, _ := mastermind.CodeToJSON(req.Guess)
	_, err = db.CreateMastermindGuess(h.db, gameID, userCtx.UserID, guessJSON, correct, misplaced, guessNum)
	if err != nil {
		jsonError(w, "failed to save guess", http.StatusInternalServerError)
		return
	}

	// Check win condition
	myGuesses, _ := db.GetMastermindGuesses(h.db, gameID, userCtx.UserID)
	opponentGuesses, _ := db.GetMastermindGuesses(h.db, gameID, opponentID)

	mySecret, _ := db.GetMastermindSecret(h.db, gameID, userCtx.UserID)
	mySecretCode, _ := mastermind.CodeFromJSON(mySecret.Code)

	// Convert to GuessResult format
	var p1Guesses, p2Guesses []mastermind.GuessResult
	var p1Secret, p2Secret []int

	if userCtx.UserID == game.Player1ID {
		p1Guesses = convertToGuessResults(myGuesses)
		p2Guesses = convertToGuessResults(opponentGuesses)
		p1Secret = mySecretCode
		p2Secret = secretCode
	} else {
		p1Guesses = convertToGuessResults(opponentGuesses)
		p2Guesses = convertToGuessResults(myGuesses)
		p1Secret = secretCode
		p2Secret = mySecretCode
	}

	gameOver, winnerID := mastermind.CheckWinCondition(
		game.Player1ID, game.Player2ID,
		p1Guesses, p2Guesses,
		p1Secret, p2Secret,
		game.MaxGuesses,
	)

	if gameOver {
		game.Status = "completed"
		game.WinnerID = winnerID
		db.UpdateMastermindGame(h.db, game)
	} else {
		// Switch turns
		game.CurrentTurn = opponentID
		db.UpdateMastermindGame(h.db, game)
	}

	// Refetch game for response
	game, _ = db.GetMastermindGame(h.db, gameID)

	response := h.buildMastermindResponse(game, userCtx.UserID)
	jsonResponse(w, response, http.StatusOK)
}

func (h *Handler) ResignMastermindGame(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	gameID := extractGameID(r)
	if gameID == 0 {
		jsonError(w, "invalid game ID", http.StatusBadRequest)
		return
	}

	game, err := db.GetMastermindGame(h.db, gameID)
	if err == db.ErrGameNotFound {
		jsonError(w, "game not found", http.StatusNotFound)
		return
	}
	if err != nil {
		jsonError(w, "failed to get game", http.StatusInternalServerError)
		return
	}

	// Check user is a player
	if game.Player1ID != userCtx.UserID && game.Player2ID != userCtx.UserID {
		jsonError(w, "not a player in this game", http.StatusForbidden)
		return
	}

	if game.Status == "completed" {
		jsonError(w, "game is already completed", http.StatusBadRequest)
		return
	}

	// Set winner as opponent
	game.Status = "completed"
	if userCtx.UserID == game.Player1ID {
		game.WinnerID = &game.Player2ID
	} else {
		game.WinnerID = &game.Player1ID
	}

	db.UpdateMastermindGame(h.db, game)

	jsonResponse(w, map[string]string{"status": "resigned"}, http.StatusOK)
}

// Helper functions

func (h *Handler) buildMastermindResponse(game *models.MastermindGame, userID int64) models.MastermindGameResponse {
	opponentID := game.Player1ID
	if userID == game.Player1ID {
		opponentID = game.Player2ID
	}

	// Get guesses
	myGuesses, _ := db.GetMastermindGuesses(h.db, game.ID, userID)
	theirGuesses, _ := db.GetMastermindGuesses(h.db, game.ID, opponentID)

	// Convert to response format
	myGuessResponses := convertToGuessResponses(myGuesses)
	theirGuessResponses := convertToGuessResponses(theirGuesses)

	// Check if user has set secret
	hasSecret, _ := db.HasUserSetSecret(h.db, game.ID, userID)

	// Determine turn
	isYourTurn := false
	switch game.Status {
	case "setup":
		isYourTurn = !hasSecret
	case "active":
		isYourTurn = game.CurrentTurn == userID
	}

	// Calculate round (max of both players' guess counts)
	round := max(len(myGuesses), len(theirGuesses))

	response := models.MastermindGameResponse{
		Game:         game,
		MyGuesses:    myGuessResponses,
		TheirGuesses: theirGuessResponses,
		SecretSet:    hasSecret,
		IsYourTurn:   isYourTurn,
		Phase:        game.Status,
		Round:        round,
	}

	// Show my secret (for display)
	if hasSecret {
		mySecret, _ := db.GetMastermindSecret(h.db, game.ID, userID)
		if mySecret != nil {
			code, _ := mastermind.CodeFromJSON(mySecret.Code)
			response.MySecret = code
		}
	}

	// Show opponent's secret only when game is completed
	if game.Status == "completed" {
		opponentSecret, _ := db.GetMastermindSecret(h.db, game.ID, opponentID)
		if opponentSecret != nil {
			code, _ := mastermind.CodeFromJSON(opponentSecret.Code)
			response.OpponentSecret = code
		}
	}

	return response
}

func convertToGuessResults(guesses []models.MastermindGuess) []mastermind.GuessResult {
	results := make([]mastermind.GuessResult, len(guesses))
	for i, g := range guesses {
		code, _ := mastermind.CodeFromJSON(g.Guess)
		results[i] = mastermind.GuessResult{
			Guess:       code,
			Correct:     g.Correct,
			Misplaced:   g.Misplaced,
			GuessNumber: g.GuessNumber,
		}
	}
	return results
}

func convertToGuessResponses(guesses []models.MastermindGuess) []models.MastermindGuessResponse {
	responses := make([]models.MastermindGuessResponse, len(guesses))
	for i, g := range guesses {
		code, _ := mastermind.CodeFromJSON(g.Guess)
		responses[i] = models.MastermindGuessResponse{
			ID:          g.ID,
			GameID:      g.GameID,
			UserID:      g.UserID,
			Guess:       code,
			Correct:     g.Correct,
			Misplaced:   g.Misplaced,
			GuessNumber: g.GuessNumber,
			CreatedAt:   g.CreatedAt,
		}
	}
	return responses
}
