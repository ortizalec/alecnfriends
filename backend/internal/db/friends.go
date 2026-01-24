package db

import (
	"crypto/rand"
	"database/sql"
	"encoding/hex"
	"errors"

	"altech/internal/models"
)

var (
	ErrFriendRequestExists   = errors.New("friend request already exists")
	ErrAlreadyFriends        = errors.New("already friends")
	ErrCannotAddSelf         = errors.New("cannot add yourself as a friend")
	ErrFriendRequestNotFound = errors.New("friend request not found")
	ErrFriendshipNotFound    = errors.New("friendship not found")
)

func GenerateFriendCode() (string, error) {
	bytes := make([]byte, 4)
	if _, err := rand.Read(bytes); err != nil {
		return "", err
	}
	return hex.EncodeToString(bytes), nil
}

func SetUserFriendCode(db *sql.DB, userID int64, code string) error {
	_, err := db.Exec("UPDATE users SET friend_code = ? WHERE id = ?", code, userID)
	return err
}

func GetUserByFriendCode(db *sql.DB, code string) (*models.User, error) {
	user := &models.User{}
	err := db.QueryRow(
		"SELECT id, username, password_hash, friend_code, created_at, updated_at FROM users WHERE friend_code = ?",
		code,
	).Scan(&user.ID, &user.Username, &user.PasswordHash, &user.FriendCode, &user.CreatedAt, &user.UpdatedAt)

	if err == sql.ErrNoRows {
		return nil, ErrUserNotFound
	}
	if err != nil {
		return nil, err
	}

	return user, nil
}

func AreFriends(db *sql.DB, userID, friendID int64) (bool, error) {
	var count int
	err := db.QueryRow(
		"SELECT COUNT(*) FROM friendships WHERE user_id = ? AND friend_id = ?",
		userID, friendID,
	).Scan(&count)
	if err != nil {
		return false, err
	}
	return count > 0, nil
}

func HasPendingRequest(db *sql.DB, senderID, receiverID int64) (bool, error) {
	var count int
	err := db.QueryRow(
		"SELECT COUNT(*) FROM friend_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'",
		senderID, receiverID,
	).Scan(&count)
	if err != nil {
		return false, err
	}
	return count > 0, nil
}

func CreateFriendRequest(db *sql.DB, senderID, receiverID int64) (*models.FriendRequest, error) {
	if senderID == receiverID {
		return nil, ErrCannotAddSelf
	}

	// Check if already friends
	friends, err := AreFriends(db, senderID, receiverID)
	if err != nil {
		return nil, err
	}
	if friends {
		return nil, ErrAlreadyFriends
	}

	// Check for existing pending request in either direction
	hasPending, err := HasPendingRequest(db, senderID, receiverID)
	if err != nil {
		return nil, err
	}
	if hasPending {
		return nil, ErrFriendRequestExists
	}

	hasPending, err = HasPendingRequest(db, receiverID, senderID)
	if err != nil {
		return nil, err
	}
	if hasPending {
		return nil, ErrFriendRequestExists
	}

	// Delete any old non-pending requests between these users so we can create a new one
	_, err = db.Exec(
		"DELETE FROM friend_requests WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND status != 'pending'",
		senderID, receiverID, receiverID, senderID,
	)
	if err != nil {
		return nil, err
	}

	result, err := db.Exec(
		"INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')",
		senderID, receiverID,
	)
	if err != nil {
		return nil, err
	}

	id, _ := result.LastInsertId()
	return GetFriendRequestByID(db, id)
}

func GetFriendRequestByID(db *sql.DB, id int64) (*models.FriendRequest, error) {
	req := &models.FriendRequest{}
	err := db.QueryRow(
		"SELECT id, sender_id, receiver_id, status, created_at FROM friend_requests WHERE id = ?",
		id,
	).Scan(&req.ID, &req.SenderID, &req.ReceiverID, &req.Status, &req.CreatedAt)

	if err == sql.ErrNoRows {
		return nil, ErrFriendRequestNotFound
	}
	if err != nil {
		return nil, err
	}

	return req, nil
}

func GetPendingFriendRequests(db *sql.DB, userID int64) ([]models.FriendRequest, error) {
	rows, err := db.Query(`
		SELECT fr.id, fr.sender_id, fr.receiver_id, fr.status, fr.created_at,
		       u.id, u.username, u.friend_code, u.created_at, u.updated_at
		FROM friend_requests fr
		JOIN users u ON u.id = fr.sender_id
		WHERE fr.receiver_id = ? AND fr.status = 'pending'
		ORDER BY fr.created_at DESC
	`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var requests []models.FriendRequest
	for rows.Next() {
		var req models.FriendRequest
		var sender models.User
		err := rows.Scan(
			&req.ID, &req.SenderID, &req.ReceiverID, &req.Status, &req.CreatedAt,
			&sender.ID, &sender.Username, &sender.FriendCode, &sender.CreatedAt, &sender.UpdatedAt,
		)
		if err != nil {
			return nil, err
		}
		req.Sender = &sender
		requests = append(requests, req)
	}

	return requests, nil
}

func AcceptFriendRequest(db *sql.DB, requestID, userID int64) error {
	// Get the request
	req, err := GetFriendRequestByID(db, requestID)
	if err != nil {
		return err
	}

	// Verify the user is the receiver
	if req.ReceiverID != userID {
		return ErrFriendRequestNotFound
	}

	if req.Status != "pending" {
		return errors.New("friend request is not pending")
	}

	// Start transaction
	tx, err := db.Begin()
	if err != nil {
		return err
	}
	defer tx.Rollback()

	// Update request status
	_, err = tx.Exec("UPDATE friend_requests SET status = 'accepted' WHERE id = ?", requestID)
	if err != nil {
		return err
	}

	// Create bidirectional friendship
	_, err = tx.Exec("INSERT INTO friendships (user_id, friend_id) VALUES (?, ?)", req.SenderID, req.ReceiverID)
	if err != nil {
		return err
	}
	_, err = tx.Exec("INSERT INTO friendships (user_id, friend_id) VALUES (?, ?)", req.ReceiverID, req.SenderID)
	if err != nil {
		return err
	}

	return tx.Commit()
}

func DenyFriendRequest(db *sql.DB, requestID, userID int64) error {
	req, err := GetFriendRequestByID(db, requestID)
	if err != nil {
		return err
	}

	if req.ReceiverID != userID {
		return ErrFriendRequestNotFound
	}

	_, err = db.Exec("UPDATE friend_requests SET status = 'denied' WHERE id = ?", requestID)
	return err
}

func GetFriends(db *sql.DB, userID int64) ([]models.Friendship, error) {
	rows, err := db.Query(`
		SELECT f.id, f.user_id, f.friend_id, f.created_at,
		       u.id, u.username, u.friend_code, u.created_at, u.updated_at
		FROM friendships f
		JOIN users u ON u.id = f.friend_id
		WHERE f.user_id = ?
		ORDER BY u.username
	`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var friendships []models.Friendship
	for rows.Next() {
		var fs models.Friendship
		var friend models.User
		err := rows.Scan(
			&fs.ID, &fs.UserID, &fs.FriendID, &fs.CreatedAt,
			&friend.ID, &friend.Username, &friend.FriendCode, &friend.CreatedAt, &friend.UpdatedAt,
		)
		if err != nil {
			return nil, err
		}
		fs.Friend = &friend
		friendships = append(friendships, fs)
	}

	return friendships, nil
}

func RemoveFriend(db *sql.DB, userID, friendID int64) error {
	// Check if friendship exists
	friends, err := AreFriends(db, userID, friendID)
	if err != nil {
		return err
	}
	if !friends {
		return ErrFriendshipNotFound
	}

	// Remove bidirectional friendship
	tx, err := db.Begin()
	if err != nil {
		return err
	}
	defer tx.Rollback()

	_, err = tx.Exec("DELETE FROM friendships WHERE user_id = ? AND friend_id = ?", userID, friendID)
	if err != nil {
		return err
	}
	_, err = tx.Exec("DELETE FROM friendships WHERE user_id = ? AND friend_id = ?", friendID, userID)
	if err != nil {
		return err
	}

	return tx.Commit()
}
