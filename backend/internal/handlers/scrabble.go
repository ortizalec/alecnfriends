package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"
	"strings"

	"altech/internal/db"
	"altech/internal/middleware"
	"altech/internal/models"
	"altech/internal/scrabble"
)

func (h *Handler) GetScrabbleGames(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	games, err := db.GetScrabbleGamesForUser(h.db, userCtx.UserID)
	if err != nil {
		jsonError(w, "failed to get games", http.StatusInternalServerError)
		return
	}

	// Categorize games
	response := models.ScrabbleGamesListResponse{
		YourTurn:  []models.ScrabbleGame{},
		TheirTurn: []models.ScrabbleGame{},
		Completed: []models.ScrabbleGame{},
	}

	for _, game := range games {
		if game.Status != "active" {
			response.Completed = append(response.Completed, game)
		} else if game.CurrentTurn == userCtx.UserID {
			response.YourTurn = append(response.YourTurn, game)
		} else {
			response.TheirTurn = append(response.TheirTurn, game)
		}
	}

	jsonResponse(w, response, http.StatusOK)
}

func (h *Handler) CreateScrabbleGame(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	var req models.CreateScrabbleGameRequest
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

	// Initialize game
	tileBag := scrabble.CreateTileBag()
	board := scrabble.CreateEmptyBoard()

	// Draw tiles for both players
	player1Tiles, remaining := scrabble.DrawTiles(tileBag, 7)
	player2Tiles, remaining := scrabble.DrawTiles(remaining, 7)

	tileBagJSON, _ := scrabble.TileBagToJSON(remaining)
	boardJSON, _ := scrabble.BoardToJSON(board)

	// Create game
	game, err := db.CreateScrabbleGame(h.db, userCtx.UserID, req.OpponentID, tileBagJSON, boardJSON)
	if err != nil {
		jsonError(w, "failed to create game", http.StatusInternalServerError)
		return
	}

	// Create racks
	player1RackJSON, _ := scrabble.RackToJSON(player1Tiles)
	player2RackJSON, _ := scrabble.RackToJSON(player2Tiles)

	db.CreateScrabbleRack(h.db, game.ID, userCtx.UserID, player1RackJSON)
	db.CreateScrabbleRack(h.db, game.ID, req.OpponentID, player2RackJSON)

	// Parse board for response
	game.Board, _ = scrabble.BoardFromJSON(boardJSON)

	jsonResponse(w, models.ScrabbleGameResponse{
		Game:           game,
		Rack:           player1Tiles,
		IsYourTurn:     true,
		TilesRemaining: len(remaining),
	}, http.StatusCreated)
}

func (h *Handler) GetScrabbleGame(w http.ResponseWriter, r *http.Request) {
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

	game, err := db.GetScrabbleGame(h.db, gameID)
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

	// Parse board
	game.Board, _ = scrabble.BoardFromJSON(game.BoardState)

	// Get user's rack
	rackJSON, _ := db.GetScrabbleRack(h.db, gameID, userCtx.UserID)
	rack, _ := scrabble.RackFromJSON(rackJSON)

	// Get tile bag count
	tileBag, _ := scrabble.TileBagFromJSON(game.TileBag)

	// Get last move for highlighting
	lastMove, _ := db.GetLastScrabbleMove(h.db, gameID)

	jsonResponse(w, models.ScrabbleGameResponse{
		Game:           game,
		Rack:           rack,
		IsYourTurn:     game.CurrentTurn == userCtx.UserID,
		TilesRemaining: len(tileBag),
		LastMove:       lastMove,
	}, http.StatusOK)
}

func (h *Handler) PlayScrabbleMove(w http.ResponseWriter, r *http.Request) {
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

	var req models.PlayMoveRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	game, err := db.GetScrabbleGame(h.db, gameID)
	if err == db.ErrGameNotFound {
		jsonError(w, "game not found", http.StatusNotFound)
		return
	}
	if err != nil {
		jsonError(w, "failed to get game", http.StatusInternalServerError)
		return
	}

	// Validate turn and game state
	if game.Status != "active" {
		jsonError(w, "game is not active", http.StatusBadRequest)
		return
	}
	if game.CurrentTurn != userCtx.UserID {
		jsonError(w, "not your turn", http.StatusBadRequest)
		return
	}

	// Get board and rack
	board, _ := scrabble.BoardFromJSON(game.BoardState)
	rackJSON, _ := db.GetScrabbleRack(h.db, gameID, userCtx.UserID)
	rack, _ := scrabble.RackFromJSON(rackJSON)

	// Validate and score move
	score, words, err := scrabble.ValidateAndScoreMove(board, rack, req.Tiles)
	if err != nil {
		jsonError(w, err.Error(), http.StatusBadRequest)
		return
	}

	// Apply move
	newBoard := scrabble.ApplyMove(board, rack, req.Tiles)
	newRack := scrabble.RemoveTilesFromRack(rack, req.Tiles)

	// Draw new tiles
	bag, _ := scrabble.TileBagFromJSON(game.TileBag)
	newRack, newBag := scrabble.RefillRack(newRack, bag)

	// Update scores
	if userCtx.UserID == game.Player1ID {
		game.Player1Score += score
	} else {
		game.Player2Score += score
	}

	// Switch turn
	if game.CurrentTurn == game.Player1ID {
		game.CurrentTurn = game.Player2ID
	} else {
		game.CurrentTurn = game.Player1ID
	}

	// Reset consecutive passes
	game.ConsecutivePasses = 0

	// Check for game end (player used all tiles and bag is empty)
	if len(newRack) == 0 && len(newBag) == 0 {
		game.Status = "completed"
		// Subtract remaining tiles from opponent, add to winner
		opponentID := game.Player1ID
		if userCtx.UserID == game.Player1ID {
			opponentID = game.Player2ID
		}
		opponentRackJSON, _ := db.GetScrabbleRack(h.db, gameID, opponentID)
		opponentRack, _ := scrabble.RackFromJSON(opponentRackJSON)

		remainingValue := 0
		for _, tile := range opponentRack {
			remainingValue += tile.Value
		}

		if userCtx.UserID == game.Player1ID {
			game.Player1Score += remainingValue
			game.Player2Score -= remainingValue
		} else {
			game.Player2Score += remainingValue
			game.Player1Score -= remainingValue
		}

		// Determine winner
		if game.Player1Score > game.Player2Score {
			game.WinnerID = &game.Player1ID
		} else if game.Player2Score > game.Player1Score {
			game.WinnerID = &game.Player2ID
		}
	}

	// Save updates
	game.BoardState, _ = scrabble.BoardToJSON(newBoard)
	game.TileBag, _ = scrabble.TileBagToJSON(newBag)
	newRackJSON, _ := scrabble.RackToJSON(newRack)

	db.UpdateScrabbleGame(h.db, game)
	db.UpdateScrabbleRack(h.db, gameID, userCtx.UserID, newRackJSON)

	// Record move
	tilesJSON, _ := json.Marshal(req.Tiles)
	wordsJSON, _ := json.Marshal(words)
	db.CreateScrabbleMove(h.db, gameID, userCtx.UserID, "play", string(tilesJSON), string(wordsJSON), score)

	// Prepare response
	game.Board = newBoard

	jsonResponse(w, models.ScrabbleGameResponse{
		Game:           game,
		Rack:           newRack,
		IsYourTurn:     game.CurrentTurn == userCtx.UserID,
		TilesRemaining: len(newBag),
	}, http.StatusOK)
}

func (h *Handler) PreviewScrabbleMove(w http.ResponseWriter, r *http.Request) {
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

	var req models.PlayMoveRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	game, err := db.GetScrabbleGame(h.db, gameID)
	if err != nil {
		jsonError(w, "game not found", http.StatusNotFound)
		return
	}

	// Get board and rack
	board, _ := scrabble.BoardFromJSON(game.BoardState)
	rackJSON, _ := db.GetScrabbleRack(h.db, gameID, userCtx.UserID)
	rack, _ := scrabble.RackFromJSON(rackJSON)

	// Validate and score (but don't apply)
	score, words, err := scrabble.ValidateAndScoreMove(board, rack, req.Tiles)
	if err != nil {
		jsonResponse(w, models.PreviewMoveResponse{
			Valid: false,
			Error: err.Error(),
		}, http.StatusOK)
		return
	}

	jsonResponse(w, models.PreviewMoveResponse{
		Valid: true,
		Score: score,
		Words: words,
	}, http.StatusOK)
}

func (h *Handler) PassScrabbleTurn(w http.ResponseWriter, r *http.Request) {
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

	game, err := db.GetScrabbleGame(h.db, gameID)
	if err == db.ErrGameNotFound {
		jsonError(w, "game not found", http.StatusNotFound)
		return
	}
	if err != nil {
		jsonError(w, "failed to get game", http.StatusInternalServerError)
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

	// Switch turn
	if game.CurrentTurn == game.Player1ID {
		game.CurrentTurn = game.Player2ID
	} else {
		game.CurrentTurn = game.Player1ID
	}

	game.ConsecutivePasses++

	// Check for game end (2 consecutive passes by each player = 4 total, or simpler: 2 passes in a row)
	// Standard rule: game ends after 2 consecutive scoreless turns
	if game.ConsecutivePasses >= 2 {
		game.Status = "completed"
		// Subtract remaining tiles from both players
		rack1JSON, _ := db.GetScrabbleRack(h.db, gameID, game.Player1ID)
		rack2JSON, _ := db.GetScrabbleRack(h.db, gameID, game.Player2ID)
		rack1, _ := scrabble.RackFromJSON(rack1JSON)
		rack2, _ := scrabble.RackFromJSON(rack2JSON)

		for _, tile := range rack1 {
			game.Player1Score -= tile.Value
		}
		for _, tile := range rack2 {
			game.Player2Score -= tile.Value
		}

		if game.Player1Score > game.Player2Score {
			game.WinnerID = &game.Player1ID
		} else if game.Player2Score > game.Player1Score {
			game.WinnerID = &game.Player2ID
		}
	}

	db.UpdateScrabbleGame(h.db, game)
	db.CreateScrabbleMove(h.db, gameID, userCtx.UserID, "pass", "", "", 0)

	// Get rack for response
	rackJSON, _ := db.GetScrabbleRack(h.db, gameID, userCtx.UserID)
	rack, _ := scrabble.RackFromJSON(rackJSON)
	game.Board, _ = scrabble.BoardFromJSON(game.BoardState)
	tileBag, _ := scrabble.TileBagFromJSON(game.TileBag)

	jsonResponse(w, models.ScrabbleGameResponse{
		Game:           game,
		Rack:           rack,
		IsYourTurn:     game.CurrentTurn == userCtx.UserID,
		TilesRemaining: len(tileBag),
	}, http.StatusOK)
}

func (h *Handler) ExchangeScrabbleTiles(w http.ResponseWriter, r *http.Request) {
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

	var req models.ExchangeTilesRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	game, err := db.GetScrabbleGame(h.db, gameID)
	if err == db.ErrGameNotFound {
		jsonError(w, "game not found", http.StatusNotFound)
		return
	}
	if err != nil {
		jsonError(w, "failed to get game", http.StatusInternalServerError)
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

	// Get rack and bag
	rackJSON, _ := db.GetScrabbleRack(h.db, gameID, userCtx.UserID)
	rack, _ := scrabble.RackFromJSON(rackJSON)
	bag, _ := scrabble.TileBagFromJSON(game.TileBag)

	// Validate exchange
	if err := scrabble.ValidateExchange(rack, req.Tiles, len(bag)); err != nil {
		jsonError(w, err.Error(), http.StatusBadRequest)
		return
	}

	// Perform exchange
	newRack, newBag := scrabble.ExchangeTiles(rack, bag, req.Tiles)

	// Switch turn
	if game.CurrentTurn == game.Player1ID {
		game.CurrentTurn = game.Player2ID
	} else {
		game.CurrentTurn = game.Player1ID
	}

	game.ConsecutivePasses++ // Exchange counts as a scoreless turn

	// Save
	game.TileBag, _ = scrabble.TileBagToJSON(newBag)
	newRackJSON, _ := scrabble.RackToJSON(newRack)

	db.UpdateScrabbleGame(h.db, game)
	db.UpdateScrabbleRack(h.db, gameID, userCtx.UserID, newRackJSON)

	tilesJSON, _ := json.Marshal(req.Tiles)
	db.CreateScrabbleMove(h.db, gameID, userCtx.UserID, "exchange", string(tilesJSON), "", 0)

	game.Board, _ = scrabble.BoardFromJSON(game.BoardState)

	jsonResponse(w, models.ScrabbleGameResponse{
		Game:           game,
		Rack:           newRack,
		IsYourTurn:     game.CurrentTurn == userCtx.UserID,
		TilesRemaining: len(newBag),
	}, http.StatusOK)
}

func (h *Handler) ResignScrabbleGame(w http.ResponseWriter, r *http.Request) {
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

	game, err := db.GetScrabbleGame(h.db, gameID)
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

	// Set winner as opponent
	game.Status = "resigned"
	if userCtx.UserID == game.Player1ID {
		game.WinnerID = &game.Player2ID
	} else {
		game.WinnerID = &game.Player1ID
	}

	db.UpdateScrabbleGame(h.db, game)
	db.CreateScrabbleMove(h.db, gameID, userCtx.UserID, "resign", "", "", 0)

	game.Board, _ = scrabble.BoardFromJSON(game.BoardState)
	rackJSON, _ := db.GetScrabbleRack(h.db, gameID, userCtx.UserID)
	rack, _ := scrabble.RackFromJSON(rackJSON)
	tileBag, _ := scrabble.TileBagFromJSON(game.TileBag)

	jsonResponse(w, models.ScrabbleGameResponse{
		Game:           game,
		Rack:           rack,
		IsYourTurn:     false,
		TilesRemaining: len(tileBag),
	}, http.StatusOK)
}

func (h *Handler) GetTileBag(w http.ResponseWriter, r *http.Request) {
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

	game, err := db.GetScrabbleGame(h.db, gameID)
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

	// Parse tile bag and aggregate counts
	tileBag, _ := scrabble.TileBagFromJSON(game.TileBag)

	// Count tiles by letter
	counts := make(map[string]int)
	for _, tile := range tileBag {
		letter := tile.Letter
		if letter == " " {
			letter = "?"
		}
		counts[letter]++
	}

	jsonResponse(w, map[string]interface{}{
		"tiles": counts,
		"total": len(tileBag),
	}, http.StatusOK)
}

func (h *Handler) GetGameHistory(w http.ResponseWriter, r *http.Request) {
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

	game, err := db.GetScrabbleGame(h.db, gameID)
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

	moves, err := db.GetScrabbleMoves(h.db, gameID)
	if err != nil {
		jsonError(w, "failed to get moves", http.StatusInternalServerError)
		return
	}

	// Enrich with player names
	type HistoryItem struct {
		MoveNumber  int      `json:"move_number"`
		PlayerName  string   `json:"player_name"`
		MoveType    string   `json:"move_type"`
		WordsFormed []string `json:"words_formed,omitempty"`
		Score       int      `json:"score"`
		CreatedAt   string   `json:"created_at"`
	}

	history := make([]HistoryItem, len(moves))
	for i, move := range moves {
		playerName := ""
		if move.UserID == game.Player1ID && game.Player1 != nil {
			playerName = game.Player1.Username
		} else if game.Player2 != nil {
			playerName = game.Player2.Username
		}

		var words []string
		if move.WordsFormed != "" {
			json.Unmarshal([]byte(move.WordsFormed), &words)
		}

		history[i] = HistoryItem{
			MoveNumber:  i + 1,
			PlayerName:  playerName,
			MoveType:    move.MoveType,
			WordsFormed: words,
			Score:       move.Score,
			CreatedAt:   move.CreatedAt.Format("Jan 2, 3:04 PM"),
		}
	}

	jsonResponse(w, map[string]interface{}{
		"history": history,
	}, http.StatusOK)
}

func extractGameID(r *http.Request) int64 {
	path := r.URL.Path
	parts := strings.Split(path, "/")

	// Find "games" in path and get the next segment
	for i, part := range parts {
		if part == "games" && i+1 < len(parts) {
			// The ID might have additional path segments after it
			idStr := parts[i+1]
			id, err := strconv.ParseInt(idStr, 10, 64)
			if err != nil {
				return 0
			}
			return id
		}
	}
	return 0
}
