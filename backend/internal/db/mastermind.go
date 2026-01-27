package db

import (
	"database/sql"
	"time"

	"altech/internal/models"
)

func CreateMastermindGame(db *sql.DB, player1ID, player2ID int64) (*models.MastermindGame, error) {
	result, err := db.Exec(`
		INSERT INTO mastermind_games (player1_id, player2_id, current_turn, status, max_guesses)
		VALUES (?, ?, ?, 'setup', 10)
	`, player1ID, player2ID, player1ID)
	if err != nil {
		return nil, err
	}

	id, err := result.LastInsertId()
	if err != nil {
		return nil, err
	}

	return GetMastermindGame(db, id)
}

func GetMastermindGame(db *sql.DB, gameID int64) (*models.MastermindGame, error) {
	game := &models.MastermindGame{}
	var winnerID sql.NullInt64

	err := db.QueryRow(`
		SELECT id, player1_id, player2_id, current_turn, status, winner_id, max_guesses, created_at, updated_at
		FROM mastermind_games WHERE id = ?
	`, gameID).Scan(
		&game.ID, &game.Player1ID, &game.Player2ID, &game.CurrentTurn,
		&game.Status, &winnerID, &game.MaxGuesses, &game.CreatedAt, &game.UpdatedAt,
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

func GetMastermindGamesForUser(db *sql.DB, userID int64) ([]models.MastermindGame, error) {
	rows, err := db.Query(`
		SELECT g.id, g.player1_id, g.player2_id, g.current_turn, g.status, g.winner_id, g.max_guesses, g.created_at, g.updated_at
		FROM mastermind_games g
		WHERE g.player1_id = ? OR g.player2_id = ?
		ORDER BY g.updated_at DESC
	`, userID, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var games []models.MastermindGame
	for rows.Next() {
		var game models.MastermindGame
		var winnerID sql.NullInt64

		err := rows.Scan(
			&game.ID, &game.Player1ID, &game.Player2ID, &game.CurrentTurn,
			&game.Status, &winnerID, &game.MaxGuesses, &game.CreatedAt, &game.UpdatedAt,
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

func UpdateMastermindGame(db *sql.DB, game *models.MastermindGame) error {
	_, err := db.Exec(`
		UPDATE mastermind_games
		SET current_turn = ?, status = ?, winner_id = ?, updated_at = ?
		WHERE id = ?
	`, game.CurrentTurn, game.Status, game.WinnerID, time.Now(), game.ID)
	return err
}

func SetMastermindSecret(db *sql.DB, gameID, userID int64, code string) error {
	_, err := db.Exec(`
		INSERT INTO mastermind_secrets (game_id, user_id, code)
		VALUES (?, ?, ?)
		ON CONFLICT(game_id, user_id) DO UPDATE SET code = excluded.code
	`, gameID, userID, code)
	return err
}

func GetMastermindSecret(db *sql.DB, gameID, userID int64) (*models.MastermindSecret, error) {
	secret := &models.MastermindSecret{}
	err := db.QueryRow(`
		SELECT id, game_id, user_id, code, created_at
		FROM mastermind_secrets
		WHERE game_id = ? AND user_id = ?
	`, gameID, userID).Scan(
		&secret.ID, &secret.GameID, &secret.UserID, &secret.Code, &secret.CreatedAt,
	)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	return secret, err
}

func BothSecretsSet(db *sql.DB, gameID int64) (bool, error) {
	var count int
	err := db.QueryRow(`
		SELECT COUNT(*) FROM mastermind_secrets
		WHERE game_id = ?
	`, gameID).Scan(&count)
	return count == 2, err
}

func CreateMastermindGuess(db *sql.DB, gameID, userID int64, guess string, correct, misplaced, guessNumber int) (*models.MastermindGuess, error) {
	result, err := db.Exec(`
		INSERT INTO mastermind_guesses (game_id, user_id, guess, correct, misplaced, guess_number)
		VALUES (?, ?, ?, ?, ?, ?)
	`, gameID, userID, guess, correct, misplaced, guessNumber)
	if err != nil {
		return nil, err
	}

	id, err := result.LastInsertId()
	if err != nil {
		return nil, err
	}

	return &models.MastermindGuess{
		ID:          id,
		GameID:      gameID,
		UserID:      userID,
		Guess:       guess,
		Correct:     correct,
		Misplaced:   misplaced,
		GuessNumber: guessNumber,
	}, nil
}

func GetMastermindGuesses(db *sql.DB, gameID, userID int64) ([]models.MastermindGuess, error) {
	rows, err := db.Query(`
		SELECT id, game_id, user_id, guess, correct, misplaced, guess_number, created_at
		FROM mastermind_guesses
		WHERE game_id = ? AND user_id = ?
		ORDER BY guess_number ASC
	`, gameID, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var guesses []models.MastermindGuess
	for rows.Next() {
		var g models.MastermindGuess
		err := rows.Scan(&g.ID, &g.GameID, &g.UserID, &g.Guess, &g.Correct, &g.Misplaced, &g.GuessNumber, &g.CreatedAt)
		if err != nil {
			return nil, err
		}
		guesses = append(guesses, g)
	}

	return guesses, nil
}

func GetLatestGuessNumber(db *sql.DB, gameID, userID int64) (int, error) {
	var guessNumber sql.NullInt64
	err := db.QueryRow(`
		SELECT MAX(guess_number) FROM mastermind_guesses
		WHERE game_id = ? AND user_id = ?
	`, gameID, userID).Scan(&guessNumber)
	if err != nil {
		return 0, err
	}
	if !guessNumber.Valid {
		return 0, nil
	}
	return int(guessNumber.Int64), nil
}

func HasUserSetSecret(db *sql.DB, gameID, userID int64) (bool, error) {
	var count int
	err := db.QueryRow(`
		SELECT COUNT(*) FROM mastermind_secrets
		WHERE game_id = ? AND user_id = ?
	`, gameID, userID).Scan(&count)
	return count > 0, err
}
