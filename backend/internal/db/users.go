package db

import (
	"database/sql"
	"errors"
	"time"

	"altech/internal/models"
)

var ErrUserNotFound = errors.New("user not found")
var ErrUserExists = errors.New("username already exists")

func CreateUser(db *sql.DB, username, passwordHash string) (*models.User, error) {
	// Generate unique friend code
	var friendCode string
	for {
		code, err := GenerateFriendCode()
		if err != nil {
			return nil, err
		}
		// Check if code already exists
		_, err = GetUserByFriendCode(db, code)
		if err == ErrUserNotFound {
			friendCode = code
			break
		}
		if err != nil {
			return nil, err
		}
		// Code exists, try again
	}

	result, err := db.Exec(
		"INSERT INTO users (username, password_hash, friend_code) VALUES (?, ?, ?)",
		username, passwordHash, friendCode,
	)
	if err != nil {
		// Check for unique constraint violation
		if err.Error() == "UNIQUE constraint failed: users.username" {
			return nil, ErrUserExists
		}
		return nil, err
	}

	id, err := result.LastInsertId()
	if err != nil {
		return nil, err
	}

	return GetUserByID(db, id)
}

func GetUserByID(db *sql.DB, id int64) (*models.User, error) {
	user := &models.User{}
	err := db.QueryRow(
		"SELECT id, username, password_hash, friend_code, created_at, updated_at FROM users WHERE id = ?",
		id,
	).Scan(&user.ID, &user.Username, &user.PasswordHash, &user.FriendCode, &user.CreatedAt, &user.UpdatedAt)

	if err == sql.ErrNoRows {
		return nil, ErrUserNotFound
	}
	if err != nil {
		return nil, err
	}

	return user, nil
}

func GetUserByUsername(db *sql.DB, username string) (*models.User, error) {
	user := &models.User{}
	err := db.QueryRow(
		"SELECT id, username, password_hash, friend_code, created_at, updated_at FROM users WHERE username = ?",
		username,
	).Scan(&user.ID, &user.Username, &user.PasswordHash, &user.FriendCode, &user.CreatedAt, &user.UpdatedAt)

	if err == sql.ErrNoRows {
		return nil, ErrUserNotFound
	}
	if err != nil {
		return nil, err
	}

	return user, nil
}

func StoreRefreshToken(db *sql.DB, userID int64, tokenHash string, expiresAt time.Time) error {
	_, err := db.Exec(
		"INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)",
		userID, tokenHash, expiresAt,
	)
	return err
}

func ValidateRefreshToken(db *sql.DB, tokenHash string) (int64, error) {
	var userID int64
	var expiresAt time.Time

	err := db.QueryRow(
		"SELECT user_id, expires_at FROM refresh_tokens WHERE token_hash = ?",
		tokenHash,
	).Scan(&userID, &expiresAt)

	if err == sql.ErrNoRows {
		return 0, errors.New("invalid refresh token")
	}
	if err != nil {
		return 0, err
	}

	if time.Now().After(expiresAt) {
		// Delete expired token
		db.Exec("DELETE FROM refresh_tokens WHERE token_hash = ?", tokenHash)
		return 0, errors.New("refresh token expired")
	}

	return userID, nil
}

func DeleteRefreshToken(db *sql.DB, tokenHash string) error {
	_, err := db.Exec("DELETE FROM refresh_tokens WHERE token_hash = ?", tokenHash)
	return err
}

func DeleteUserRefreshTokens(db *sql.DB, userID int64) error {
	_, err := db.Exec("DELETE FROM refresh_tokens WHERE user_id = ?", userID)
	return err
}
