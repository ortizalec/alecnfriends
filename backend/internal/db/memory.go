package db

import (
	"database/sql"
	"time"

	"altech/internal/models"
)

func CreateMemoryGame(db *sql.DB, player1ID, player2ID int64, boardSize, boardJSON, matchedJSON string) (*models.MemoryGame, error) {
	result, err := db.Exec(`
		INSERT INTO memory_games (player1_id, player2_id, current_turn, status, board_size, board, matched)
		VALUES (?, ?, ?, 'active', ?, ?, ?)
	`, player1ID, player2ID, player1ID, boardSize, boardJSON, matchedJSON)
	if err != nil {
		return nil, err
	}

	id, err := result.LastInsertId()
	if err != nil {
		return nil, err
	}

	return GetMemoryGame(db, id)
}

func GetMemoryGame(db *sql.DB, gameID int64) (*models.MemoryGame, error) {
	game := &models.MemoryGame{}
	var winnerID sql.NullInt64

	err := db.QueryRow(`
		SELECT id, player1_id, player2_id, current_turn, status, winner_id, board_size, board, matched, player1_score, player2_score, created_at, updated_at
		FROM memory_games WHERE id = ?
	`, gameID).Scan(
		&game.ID, &game.Player1ID, &game.Player2ID, &game.CurrentTurn,
		&game.Status, &winnerID, &game.BoardSize, &game.Board, &game.Matched,
		&game.Player1Score, &game.Player2Score,
		&game.CreatedAt, &game.UpdatedAt,
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

func GetMemoryGamesForUser(db *sql.DB, userID int64) ([]models.MemoryGame, error) {
	rows, err := db.Query(`
		SELECT g.id, g.player1_id, g.player2_id, g.current_turn, g.status, g.winner_id, g.board_size, g.board, g.matched, g.player1_score, g.player2_score, g.created_at, g.updated_at
		FROM memory_games g
		WHERE g.player1_id = ? OR g.player2_id = ?
		ORDER BY g.updated_at DESC
	`, userID, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var games []models.MemoryGame
	for rows.Next() {
		var game models.MemoryGame
		var winnerID sql.NullInt64

		err := rows.Scan(
			&game.ID, &game.Player1ID, &game.Player2ID, &game.CurrentTurn,
			&game.Status, &winnerID, &game.BoardSize, &game.Board, &game.Matched,
			&game.Player1Score, &game.Player2Score,
			&game.CreatedAt, &game.UpdatedAt,
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

func UpdateMemoryGame(db *sql.DB, game *models.MemoryGame) error {
	_, err := db.Exec(`
		UPDATE memory_games
		SET current_turn = ?, status = ?, winner_id = ?, matched = ?, player1_score = ?, player2_score = ?, updated_at = ?
		WHERE id = ?
	`, game.CurrentTurn, game.Status, game.WinnerID, game.Matched, game.Player1Score, game.Player2Score, time.Now(), game.ID)
	return err
}

func CreateMemoryMove(db *sql.DB, move *models.MemoryMove) (*models.MemoryMove, error) {
	result, err := db.Exec(`
		INSERT INTO memory_moves (game_id, user_id, row1, col1, row2, col2, tile1, tile2, matched)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
	`, move.GameID, move.UserID, move.Row1, move.Col1, move.Row2, move.Col2, move.Tile1, move.Tile2, move.Matched)
	if err != nil {
		return nil, err
	}

	id, err := result.LastInsertId()
	if err != nil {
		return nil, err
	}

	move.ID = id
	return move, nil
}

func GetMemoryMoves(db *sql.DB, gameID int64) ([]models.MemoryMove, error) {
	rows, err := db.Query(`
		SELECT id, game_id, user_id, row1, col1, row2, col2, tile1, tile2, matched, created_at
		FROM memory_moves
		WHERE game_id = ?
		ORDER BY id DESC
	`, gameID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var moves []models.MemoryMove
	for rows.Next() {
		var m models.MemoryMove
		err := rows.Scan(&m.ID, &m.GameID, &m.UserID, &m.Row1, &m.Col1, &m.Row2, &m.Col2, &m.Tile1, &m.Tile2, &m.Matched, &m.CreatedAt)
		if err != nil {
			return nil, err
		}
		moves = append(moves, m)
	}

	return moves, nil
}
