package db

import (
	"database/sql"
	"time"

	"altech/internal/models"
)

func CreateBattleshipGame(db *sql.DB, player1ID, player2ID int64) (*models.BattleshipGame, error) {
	result, err := db.Exec(`
		INSERT INTO battleship_games (player1_id, player2_id, current_turn, status)
		VALUES (?, ?, ?, 'setup')
	`, player1ID, player2ID, player1ID)
	if err != nil {
		return nil, err
	}

	id, err := result.LastInsertId()
	if err != nil {
		return nil, err
	}

	// Create boards for both players
	_, err = db.Exec(`
		INSERT INTO battleship_boards (game_id, user_id, ships, shots, ships_ready)
		VALUES (?, ?, '[]', '[]', 0), (?, ?, '[]', '[]', 0)
	`, id, player1ID, id, player2ID)
	if err != nil {
		return nil, err
	}

	return GetBattleshipGame(db, id)
}

func GetBattleshipGame(db *sql.DB, gameID int64) (*models.BattleshipGame, error) {
	game := &models.BattleshipGame{}
	var winnerID sql.NullInt64

	err := db.QueryRow(`
		SELECT id, player1_id, player2_id, current_turn, status, winner_id, created_at, updated_at
		FROM battleship_games WHERE id = ?
	`, gameID).Scan(
		&game.ID, &game.Player1ID, &game.Player2ID, &game.CurrentTurn,
		&game.Status, &winnerID, &game.CreatedAt, &game.UpdatedAt,
	)
	if err == sql.ErrNoRows {
		return nil, ErrGameNotFound
	}
	if err != nil {
		return nil, err
	}

	if winnerID.Valid {
		game.WinnerID = &winnerID.Int64
	}

	// Load player info
	game.Player1, _ = GetUserByID(db, game.Player1ID)
	game.Player2, _ = GetUserByID(db, game.Player2ID)

	return game, nil
}

func GetBattleshipGamesForUser(db *sql.DB, userID int64) ([]models.BattleshipGame, error) {
	rows, err := db.Query(`
		SELECT g.id, g.player1_id, g.player2_id, g.current_turn, g.status, g.winner_id, g.created_at, g.updated_at
		FROM battleship_games g
		WHERE g.player1_id = ? OR g.player2_id = ?
		ORDER BY g.updated_at DESC
	`, userID, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var games []models.BattleshipGame
	for rows.Next() {
		var game models.BattleshipGame
		var winnerID sql.NullInt64

		err := rows.Scan(
			&game.ID, &game.Player1ID, &game.Player2ID, &game.CurrentTurn,
			&game.Status, &winnerID, &game.CreatedAt, &game.UpdatedAt,
		)
		if err != nil {
			return nil, err
		}

		if winnerID.Valid {
			game.WinnerID = &winnerID.Int64
		}

		game.Player1, _ = GetUserByID(db, game.Player1ID)
		game.Player2, _ = GetUserByID(db, game.Player2ID)

		games = append(games, game)
	}

	return games, nil
}

func UpdateBattleshipGame(db *sql.DB, game *models.BattleshipGame) error {
	_, err := db.Exec(`
		UPDATE battleship_games
		SET current_turn = ?, status = ?, winner_id = ?, updated_at = ?
		WHERE id = ?
	`, game.CurrentTurn, game.Status, game.WinnerID, time.Now(), game.ID)
	return err
}

func GetBattleshipBoard(db *sql.DB, gameID, userID int64) (*models.BattleshipBoard, error) {
	board := &models.BattleshipBoard{}
	err := db.QueryRow(`
		SELECT id, game_id, user_id, ships, shots, ships_ready
		FROM battleship_boards
		WHERE game_id = ? AND user_id = ?
	`, gameID, userID).Scan(
		&board.ID, &board.GameID, &board.UserID, &board.Ships, &board.Shots, &board.ShipsReady,
	)
	if err == sql.ErrNoRows {
		return nil, ErrNotInGame
	}
	return board, err
}

func UpdateBattleshipBoard(db *sql.DB, board *models.BattleshipBoard) error {
	_, err := db.Exec(`
		UPDATE battleship_boards
		SET ships = ?, shots = ?, ships_ready = ?
		WHERE game_id = ? AND user_id = ?
	`, board.Ships, board.Shots, board.ShipsReady, board.GameID, board.UserID)
	return err
}

func AreBothPlayersReady(db *sql.DB, gameID int64) (bool, error) {
	var count int
	err := db.QueryRow(`
		SELECT COUNT(*) FROM battleship_boards
		WHERE game_id = ? AND ships_ready = 1
	`, gameID).Scan(&count)
	return count == 2, err
}
