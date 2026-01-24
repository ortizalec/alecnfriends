package models

import "time"

type FriendRequest struct {
	ID         int64     `json:"id"`
	SenderID   int64     `json:"sender_id"`
	ReceiverID int64     `json:"receiver_id"`
	Status     string    `json:"status"`
	CreatedAt  time.Time `json:"created_at"`
	Sender     *User     `json:"sender,omitempty"`
}

type Friendship struct {
	ID        int64     `json:"id"`
	UserID    int64     `json:"user_id"`
	FriendID  int64     `json:"friend_id"`
	CreatedAt time.Time `json:"created_at"`
	Friend    *User     `json:"friend,omitempty"`
}

type SendFriendRequestRequest struct {
	FriendCode string `json:"friend_code"`
}

type FriendRequestAction struct {
	Action string `json:"action"` // "accept" or "deny"
}
