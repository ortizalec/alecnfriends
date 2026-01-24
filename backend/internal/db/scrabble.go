package db

import (
	"database/sql"
	"errors"
	"time"

	"altech/internal/models"
)

var (
	ErrGameNotFound = errors.New("game not found")
	ErrNotInGame    = errors.New("not a player in this game")
)

func CreateScrabbleGame(db *sql.DB, player1ID, player2ID int64, tileBag, boardState string) (*models.ScrabbleGame, error) {
	result, err := db.Exec(`
		INSERT INTO scrabble_games (player1_id, player2_id, current_turn, tile_bag, board_state)
		VALUES (?, ?, ?, ?, ?)
	`, player1ID, player2ID, player1ID, tileBag, boardState)
	if err != nil {
		return nil, err
	}

	id, err := result.LastInsertId()
	if err != nil {
		return nil, err
	}

	return GetScrabbleGame(db, id)
}

func GetScrabbleGame(db *sql.DB, gameID int64) (*models.ScrabbleGame, error) {
	game := &models.ScrabbleGame{}
	var winnerID sql.NullInt64

	err := db.QueryRow(`
		SELECT id, player1_id, player2_id, current_turn, player1_score, player2_score,
		       status, winner_id, tile_bag, board_state, consecutive_passes, created_at, updated_at
		FROM scrabble_games WHERE id = ?
	`, gameID).Scan(
		&game.ID, &game.Player1ID, &game.Player2ID, &game.CurrentTurn,
		&game.Player1Score, &game.Player2Score, &game.Status, &winnerID,
		&game.TileBag, &game.BoardState, &game.ConsecutivePasses, &game.CreatedAt, &game.UpdatedAt,
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

func GetScrabbleGamesForUser(db *sql.DB, userID int64) ([]models.ScrabbleGame, error) {
	rows, err := db.Query(`
		SELECT g.id, g.player1_id, g.player2_id, g.current_turn, g.player1_score, g.player2_score,
		       g.status, g.winner_id, g.tile_bag, g.board_state, g.consecutive_passes, g.created_at, g.updated_at
		FROM scrabble_games g
		WHERE g.player1_id = ? OR g.player2_id = ?
		ORDER BY g.updated_at DESC
	`, userID, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var games []models.ScrabbleGame
	for rows.Next() {
		var game models.ScrabbleGame
		var winnerID sql.NullInt64

		err := rows.Scan(
			&game.ID, &game.Player1ID, &game.Player2ID, &game.CurrentTurn,
			&game.Player1Score, &game.Player2Score, &game.Status, &winnerID,
			&game.TileBag, &game.BoardState, &game.ConsecutivePasses, &game.CreatedAt, &game.UpdatedAt,
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

func UpdateScrabbleGame(db *sql.DB, game *models.ScrabbleGame) error {
	_, err := db.Exec(`
		UPDATE scrabble_games
		SET current_turn = ?, player1_score = ?, player2_score = ?, status = ?,
		    winner_id = ?, tile_bag = ?, board_state = ?, consecutive_passes = ?, updated_at = ?
		WHERE id = ?
	`, game.CurrentTurn, game.Player1Score, game.Player2Score, game.Status,
		game.WinnerID, game.TileBag, game.BoardState, game.ConsecutivePasses, time.Now(), game.ID)
	return err
}

func CreateScrabbleRack(db *sql.DB, gameID, userID int64, tiles string) error {
	_, err := db.Exec(`
		INSERT INTO scrabble_racks (game_id, user_id, tiles)
		VALUES (?, ?, ?)
		ON CONFLICT(game_id, user_id) DO UPDATE SET tiles = excluded.tiles
	`, gameID, userID, tiles)
	return err
}

func GetScrabbleRack(db *sql.DB, gameID, userID int64) (string, error) {
	var tiles string
	err := db.QueryRow(`SELECT tiles FROM scrabble_racks WHERE game_id = ? AND user_id = ?`, gameID, userID).Scan(&tiles)
	if err == sql.ErrNoRows {
		return "[]", nil
	}
	return tiles, err
}

func UpdateScrabbleRack(db *sql.DB, gameID, userID int64, tiles string) error {
	_, err := db.Exec(`UPDATE scrabble_racks SET tiles = ? WHERE game_id = ? AND user_id = ?`, tiles, gameID, userID)
	return err
}

func CreateScrabbleMove(db *sql.DB, gameID, userID int64, moveType, tilesPlayed, wordsFormed string, score int) error {
	_, err := db.Exec(`
		INSERT INTO scrabble_moves (game_id, user_id, move_type, tiles_played, words_formed, score)
		VALUES (?, ?, ?, ?, ?, ?)
	`, gameID, userID, moveType, tilesPlayed, wordsFormed, score)
	return err
}

func GetScrabbleMoves(db *sql.DB, gameID int64) ([]models.ScrabbleMove, error) {
	rows, err := db.Query(`
		SELECT id, game_id, user_id, move_type, tiles_played, words_formed, score, created_at
		FROM scrabble_moves WHERE game_id = ? ORDER BY created_at ASC
	`, gameID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var moves []models.ScrabbleMove
	for rows.Next() {
		var move models.ScrabbleMove
		var tilesPlayed, wordsFormed sql.NullString

		err := rows.Scan(&move.ID, &move.GameID, &move.UserID, &move.MoveType,
			&tilesPlayed, &wordsFormed, &move.Score, &move.CreatedAt)
		if err != nil {
			return nil, err
		}

		move.TilesPlayed = tilesPlayed.String
		move.WordsFormed = wordsFormed.String
		moves = append(moves, move)
	}

	return moves, nil
}

func GetLastScrabbleMove(db *sql.DB, gameID int64) (*models.ScrabbleMove, error) {
	var move models.ScrabbleMove
	var tilesPlayed, wordsFormed sql.NullString

	err := db.QueryRow(`
		SELECT id, game_id, user_id, move_type, tiles_played, words_formed, score, created_at
		FROM scrabble_moves WHERE game_id = ? ORDER BY created_at DESC LIMIT 1
	`, gameID).Scan(&move.ID, &move.GameID, &move.UserID, &move.MoveType,
		&tilesPlayed, &wordsFormed, &move.Score, &move.CreatedAt)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}

	move.TilesPlayed = tilesPlayed.String
	move.WordsFormed = wordsFormed.String
	return &move, nil
}

func CheckFriendship(db *sql.DB, userID, friendID int64) (bool, error) {
	var count int
	err := db.QueryRow(`
		SELECT COUNT(*) FROM friendships WHERE user_id = ? AND friend_id = ?
	`, userID, friendID).Scan(&count)
	return count > 0, err
}
