package handlers

import (
	"encoding/json"
	"net/http"

	"altech/internal/db"
	"altech/internal/memory"
	"altech/internal/middleware"
	"altech/internal/models"
)

func (h *Handler) GetMemoryGames(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	games, err := db.GetMemoryGamesForUser(h.db, userCtx.UserID)
	if err != nil {
		jsonError(w, "failed to get games", http.StatusInternalServerError)
		return
	}

	response := models.MemoryGamesListResponse{
		YourTurn:  []models.MemoryGame{},
		TheirTurn: []models.MemoryGame{},
		Completed: []models.MemoryGame{},
	}

	for _, game := range games {
		if game.Status == "completed" {
			response.Completed = append(response.Completed, game)
		} else if game.CurrentTurn == userCtx.UserID {
			response.YourTurn = append(response.YourTurn, game)
		} else {
			response.TheirTurn = append(response.TheirTurn, game)
		}
	}

	jsonResponse(w, response, http.StatusOK)
}

func (h *Handler) CreateMemoryGame(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	var req models.CreateMemoryGameRequest
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

	// Validate board size
	boardSize := req.BoardSize
	if boardSize == "" {
		boardSize = "4x5"
	}
	rows, cols, err := memory.ParseBoardSize(boardSize)
	if err != nil {
		jsonError(w, err.Error(), http.StatusBadRequest)
		return
	}

	// Generate board
	board := memory.GenerateBoard(rows, cols)
	boardJSON, err := memory.BoardToJSON(board)
	if err != nil {
		jsonError(w, "failed to generate board", http.StatusInternalServerError)
		return
	}

	matched := memory.InitMatched(rows, cols)
	matchedJSON, err := memory.MatchedToJSON(matched)
	if err != nil {
		jsonError(w, "failed to initialize game", http.StatusInternalServerError)
		return
	}

	game, err := db.CreateMemoryGame(h.db, userCtx.UserID, req.OpponentID, boardSize, boardJSON, matchedJSON)
	if err != nil {
		jsonError(w, "failed to create game", http.StatusInternalServerError)
		return
	}

	response := h.buildMemoryResponse(game, userCtx.UserID)
	jsonResponse(w, response, http.StatusCreated)
}

func (h *Handler) GetMemoryGame(w http.ResponseWriter, r *http.Request) {
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

	game, err := db.GetMemoryGame(h.db, gameID)
	if err == db.ErrGameNotFound {
		jsonError(w, "game not found", http.StatusNotFound)
		return
	}
	if err != nil {
		jsonError(w, "failed to get game", http.StatusInternalServerError)
		return
	}

	if game.Player1ID != userCtx.UserID && game.Player2ID != userCtx.UserID {
		jsonError(w, "not a player in this game", http.StatusForbidden)
		return
	}

	response := h.buildMemoryResponse(game, userCtx.UserID)
	jsonResponse(w, response, http.StatusOK)
}

func (h *Handler) RevealTiles(w http.ResponseWriter, r *http.Request) {
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

	var req models.RevealTilesRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	game, err := db.GetMemoryGame(h.db, gameID)
	if err == db.ErrGameNotFound {
		jsonError(w, "game not found", http.StatusNotFound)
		return
	}
	if err != nil {
		jsonError(w, "failed to get game", http.StatusInternalServerError)
		return
	}

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

	// Parse board and matched
	board, err := memory.BoardFromJSON(game.Board)
	if err != nil {
		jsonError(w, "failed to parse board", http.StatusInternalServerError)
		return
	}

	matched, err := memory.MatchedFromJSON(game.Matched)
	if err != nil {
		jsonError(w, "failed to parse matched state", http.StatusInternalServerError)
		return
	}

	rows := len(board)
	cols := len(board[0])

	// Validate positions
	if req.Row1 < 0 || req.Row1 >= rows || req.Col1 < 0 || req.Col1 >= cols ||
		req.Row2 < 0 || req.Row2 >= rows || req.Col2 < 0 || req.Col2 >= cols {
		jsonError(w, "position out of bounds", http.StatusBadRequest)
		return
	}

	if req.Row1 == req.Row2 && req.Col1 == req.Col2 {
		jsonError(w, "must select two different tiles", http.StatusBadRequest)
		return
	}

	if matched[req.Row1][req.Col1] || matched[req.Row2][req.Col2] {
		jsonError(w, "tile already matched", http.StatusBadRequest)
		return
	}

	// Reveal tiles
	tile1 := board[req.Row1][req.Col1]
	tile2 := board[req.Row2][req.Col2]
	isMatch := memory.CheckMatch(board, req.Row1, req.Col1, req.Row2, req.Col2)

	// Record move
	move := &models.MemoryMove{
		GameID:  gameID,
		UserID:  userCtx.UserID,
		Row1:    req.Row1,
		Col1:    req.Col1,
		Row2:    req.Row2,
		Col2:    req.Col2,
		Tile1:   tile1,
		Tile2:   tile2,
		Matched: isMatch,
	}
	db.CreateMemoryMove(h.db, move)

	if isMatch {
		matched[req.Row1][req.Col1] = true
		matched[req.Row2][req.Col2] = true

		// Update score
		if userCtx.UserID == game.Player1ID {
			game.Player1Score++
		} else {
			game.Player2Score++
		}
	}

	// Update matched state
	matchedJSON, _ := memory.MatchedToJSON(matched)
	game.Matched = matchedJSON

	// Check if game is over
	totalPairs := (rows * cols) / 2
	matchedCount := memory.CountMatched(matched)
	gameOver := matchedCount == totalPairs

	if gameOver {
		game.Status = "completed"
		if game.Player1Score > game.Player2Score {
			game.WinnerID = &game.Player1ID
		} else if game.Player2Score > game.Player1Score {
			game.WinnerID = &game.Player2ID
		}
		// nil WinnerID = draw
	} else if !isMatch {
		// Switch turns
		if game.CurrentTurn == game.Player1ID {
			game.CurrentTurn = game.Player2ID
		} else {
			game.CurrentTurn = game.Player1ID
		}
	}
	// If match, current player keeps their turn (no change)

	db.UpdateMemoryGame(h.db, game)

	jsonResponse(w, models.RevealTilesResponse{
		Tile1:     tile1,
		Tile2:     tile2,
		Matched:   isMatch,
		ExtraTurn: isMatch && !gameOver,
		GameOver:  gameOver,
	}, http.StatusOK)
}

func (h *Handler) ResignMemoryGame(w http.ResponseWriter, r *http.Request) {
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

	game, err := db.GetMemoryGame(h.db, gameID)
	if err == db.ErrGameNotFound {
		jsonError(w, "game not found", http.StatusNotFound)
		return
	}
	if err != nil {
		jsonError(w, "failed to get game", http.StatusInternalServerError)
		return
	}

	if game.Player1ID != userCtx.UserID && game.Player2ID != userCtx.UserID {
		jsonError(w, "not a player in this game", http.StatusForbidden)
		return
	}

	if game.Status == "completed" {
		jsonError(w, "game is already completed", http.StatusBadRequest)
		return
	}

	game.Status = "completed"
	if userCtx.UserID == game.Player1ID {
		game.WinnerID = &game.Player2ID
	} else {
		game.WinnerID = &game.Player1ID
	}

	db.UpdateMemoryGame(h.db, game)

	jsonResponse(w, map[string]string{"status": "resigned"}, http.StatusOK)
}

// buildMemoryResponse constructs the response with visible board state.
func (h *Handler) buildMemoryResponse(game *models.MemoryGame, userID int64) models.MemoryGameResponse {
	board, _ := memory.BoardFromJSON(game.Board)
	matched, _ := memory.MatchedFromJSON(game.Matched)

	visibleBoard := memory.BuildVisibleBoard(board, matched)
	matchedCount := memory.CountMatched(matched)

	rows := len(board)
	cols := len(board[0])
	totalPairs := (rows * cols) / 2

	moves, _ := db.GetMemoryMoves(h.db, game.ID)
	if moves == nil {
		moves = []models.MemoryMove{}
	}

	isYourTurn := game.Status == "active" && game.CurrentTurn == userID

	resp := models.MemoryGameResponse{
		Game:         game,
		Board:        visibleBoard,
		IsYourTurn:   isYourTurn,
		Moves:        moves,
		TotalPairs:   totalPairs,
		MatchedCount: matchedCount,
	}

	// Send full board to the current turn player so they can reveal tiles client-side
	if isYourTurn {
		resp.FullBoard = board
	}

	return resp
}
