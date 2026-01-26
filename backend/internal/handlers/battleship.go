package handlers

import (
	"encoding/json"
	"net/http"

	"altech/internal/battleship"
	"altech/internal/db"
	"altech/internal/middleware"
	"altech/internal/models"
)

func (h *Handler) GetBattleshipGames(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	games, err := db.GetBattleshipGamesForUser(h.db, userCtx.UserID)
	if err != nil {
		jsonError(w, "failed to get games", http.StatusInternalServerError)
		return
	}

	response := models.BattleshipGamesListResponse{
		YourTurn:  []models.BattleshipGame{},
		TheirTurn: []models.BattleshipGame{},
		Completed: []models.BattleshipGame{},
	}

	for _, game := range games {
		if game.Status == "completed" {
			response.Completed = append(response.Completed, game)
		} else if game.Status == "setup" {
			// During setup, check if current user has placed ships
			board, _ := db.GetBattleshipBoard(h.db, game.ID, userCtx.UserID)
			if board != nil && !board.ShipsReady {
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

func (h *Handler) CreateBattleshipGame(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	var req models.CreateBattleshipGameRequest
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

	game, err := db.CreateBattleshipGame(h.db, userCtx.UserID, req.OpponentID)
	if err != nil {
		jsonError(w, "failed to create game", http.StatusInternalServerError)
		return
	}

	jsonResponse(w, models.BattleshipGameResponse{
		Game:       game,
		MyBoard:    battleship.CreateEmptyBoard(),
		EnemyBoard: battleship.CreateEmptyBoard(),
		MyShips:    []models.Ship{},
		IsYourTurn: true,
		ShipsReady: false,
		Phase:      "setup",
	}, http.StatusCreated)
}

func (h *Handler) GetBattleshipGame(w http.ResponseWriter, r *http.Request) {
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

	game, err := db.GetBattleshipGame(h.db, gameID)
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

	// Get my board
	myBoard, err := db.GetBattleshipBoard(h.db, gameID, userCtx.UserID)
	if err != nil {
		jsonError(w, "failed to get board", http.StatusInternalServerError)
		return
	}

	// Get opponent's board (for shots I've fired)
	opponentID := game.Player1ID
	if userCtx.UserID == game.Player1ID {
		opponentID = game.Player2ID
	}
	opponentBoard, _ := db.GetBattleshipBoard(h.db, gameID, opponentID)

	// Parse ships and shots
	myShips, _ := battleship.ShipsFromJSON(myBoard.Ships)
	shotsReceived, _ := battleship.ShotsFromJSON(myBoard.Shots)

	var shotsFired []models.Shot
	var enemyShips []models.Ship
	if opponentBoard != nil {
		shotsFired, _ = battleship.ShotsFromJSON(opponentBoard.Shots)
		enemyShips, _ = battleship.ShipsFromJSON(opponentBoard.Ships)
	}

	// Build board views
	myBoardView := battleship.BuildMyBoard(myShips, shotsReceived)
	enemyBoardView := battleship.BuildEnemyBoard(shotsFired)

	// Calculate enemy ships remaining (not sunk)
	enemyShipsRemaining := 0
	for _, ship := range enemyShips {
		if ship.Hits < ship.Size {
			enemyShipsRemaining++
		}
	}
	// If enemy hasn't placed ships yet, show 5 (all ships)
	if len(enemyShips) == 0 && game.Status != "setup" {
		enemyShipsRemaining = 5
	}

	// Determine if it's my turn
	isYourTurn := false
	if game.Status == "setup" {
		isYourTurn = !myBoard.ShipsReady
	} else if game.Status == "active" {
		isYourTurn = game.CurrentTurn == userCtx.UserID
	}

	jsonResponse(w, models.BattleshipGameResponse{
		Game:                game,
		MyBoard:             myBoardView,
		EnemyBoard:          enemyBoardView,
		MyShips:             myShips,
		IsYourTurn:          isYourTurn,
		ShipsReady:          myBoard.ShipsReady,
		Phase:               game.Status,
		EnemyShipsRemaining: enemyShipsRemaining,
	}, http.StatusOK)
}

func (h *Handler) PlaceBattleshipShips(w http.ResponseWriter, r *http.Request) {
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

	var req models.PlaceShipsRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	game, err := db.GetBattleshipGame(h.db, gameID)
	if err == db.ErrGameNotFound {
		jsonError(w, "game not found", http.StatusNotFound)
		return
	}
	if err != nil {
		jsonError(w, "failed to get game", http.StatusInternalServerError)
		return
	}

	if game.Status != "setup" {
		jsonError(w, "game is not in setup phase", http.StatusBadRequest)
		return
	}

	// Validate ship placement
	// Set sizes based on type
	for i := range req.Ships {
		req.Ships[i].Size = battleship.ShipSizes[req.Ships[i].Type]
	}

	if err := battleship.ValidateShipPlacement(req.Ships); err != nil {
		jsonError(w, err.Error(), http.StatusBadRequest)
		return
	}

	// Get and update board
	board, err := db.GetBattleshipBoard(h.db, gameID, userCtx.UserID)
	if err != nil {
		jsonError(w, "failed to get board", http.StatusInternalServerError)
		return
	}

	if board.ShipsReady {
		jsonError(w, "ships already placed", http.StatusBadRequest)
		return
	}

	shipsJSON, _ := battleship.ShipsToJSON(req.Ships)
	board.Ships = shipsJSON
	board.ShipsReady = true

	if err := db.UpdateBattleshipBoard(h.db, board); err != nil {
		jsonError(w, "failed to save ships", http.StatusInternalServerError)
		return
	}

	// Check if both players are ready
	bothReady, _ := db.AreBothPlayersReady(h.db, gameID)
	if bothReady {
		game.Status = "active"
		db.UpdateBattleshipGame(h.db, game)
	}

	// Refetch game for updated status
	game, _ = db.GetBattleshipGame(h.db, gameID)

	jsonResponse(w, models.BattleshipGameResponse{
		Game:       game,
		MyBoard:    battleship.BuildMyBoard(req.Ships, nil),
		EnemyBoard: battleship.CreateEmptyBoard(),
		MyShips:    req.Ships,
		IsYourTurn: game.Status == "active" && game.CurrentTurn == userCtx.UserID,
		ShipsReady: true,
		Phase:      game.Status,
	}, http.StatusOK)
}

func (h *Handler) FireBattleshipShot(w http.ResponseWriter, r *http.Request) {
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

	var req models.FireShotRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	game, err := db.GetBattleshipGame(h.db, gameID)
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

	// Get opponent's board
	opponentID := game.Player1ID
	if userCtx.UserID == game.Player1ID {
		opponentID = game.Player2ID
	}
	opponentBoard, err := db.GetBattleshipBoard(h.db, gameID, opponentID)
	if err != nil {
		jsonError(w, "failed to get opponent board", http.StatusInternalServerError)
		return
	}

	// Parse opponent's ships and shots received
	opponentShips, _ := battleship.ShipsFromJSON(opponentBoard.Ships)
	shotsOnOpponent, _ := battleship.ShotsFromJSON(opponentBoard.Shots)

	// Process the shot
	hit, sunk, shipType, err := battleship.ProcessShot(opponentShips, shotsOnOpponent, req.Row, req.Col)
	if err != nil {
		jsonError(w, err.Error(), http.StatusBadRequest)
		return
	}

	// Record the shot
	shotsOnOpponent = append(shotsOnOpponent, models.Shot{Row: req.Row, Col: req.Col, Hit: hit})
	opponentBoard.Shots, _ = battleship.ShotsToJSON(shotsOnOpponent)
	opponentBoard.Ships, _ = battleship.ShipsToJSON(opponentShips) // Update hits on ships

	if err := db.UpdateBattleshipBoard(h.db, opponentBoard); err != nil {
		jsonError(w, "failed to save shot", http.StatusInternalServerError)
		return
	}

	// Check for game over
	gameOver := battleship.CheckAllShipsSunk(opponentShips)
	winnerName := ""
	if gameOver {
		game.Status = "completed"
		game.WinnerID = &userCtx.UserID
		db.UpdateBattleshipGame(h.db, game)

		// Get winner name
		user, _ := db.GetUserByID(h.db, userCtx.UserID)
		if user != nil {
			winnerName = user.Username
		}
	} else {
		// Switch turns
		game.CurrentTurn = opponentID
		db.UpdateBattleshipGame(h.db, game)
	}

	jsonResponse(w, models.FireShotResponse{
		Hit:      hit,
		Sunk:     sunk,
		ShipType: shipType,
		GameOver: gameOver,
		Winner:   winnerName,
	}, http.StatusOK)
}

func (h *Handler) ResignBattleshipGame(w http.ResponseWriter, r *http.Request) {
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

	game, err := db.GetBattleshipGame(h.db, gameID)
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

	db.UpdateBattleshipGame(h.db, game)

	jsonResponse(w, map[string]string{"status": "resigned"}, http.StatusOK)
}
