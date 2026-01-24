package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"
	"strings"

	"altech/internal/db"
	"altech/internal/middleware"
	"altech/internal/models"
)

func (h *Handler) GetFriends(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	friends, err := db.GetFriends(h.db, userCtx.UserID)
	if err != nil {
		jsonError(w, "failed to get friends", http.StatusInternalServerError)
		return
	}

	if friends == nil {
		friends = []models.Friendship{}
	}

	jsonResponse(w, friends, http.StatusOK)
}

func (h *Handler) SendFriendRequest(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	var req models.SendFriendRequestRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	req.FriendCode = strings.TrimSpace(req.FriendCode)
	if req.FriendCode == "" {
		jsonError(w, "friend code is required", http.StatusBadRequest)
		return
	}

	// Find user by friend code
	targetUser, err := db.GetUserByFriendCode(h.db, req.FriendCode)
	if err == db.ErrUserNotFound {
		jsonError(w, "invalid friend code", http.StatusNotFound)
		return
	}
	if err != nil {
		jsonError(w, "failed to find user", http.StatusInternalServerError)
		return
	}

	// Create friend request
	friendReq, err := db.CreateFriendRequest(h.db, userCtx.UserID, targetUser.ID)
	if err == db.ErrCannotAddSelf {
		jsonError(w, "cannot add yourself as a friend", http.StatusBadRequest)
		return
	}
	if err == db.ErrAlreadyFriends {
		jsonError(w, "already friends with this user", http.StatusConflict)
		return
	}
	if err == db.ErrFriendRequestExists {
		jsonError(w, "friend request already pending", http.StatusConflict)
		return
	}
	if err != nil {
		jsonError(w, "failed to create friend request", http.StatusInternalServerError)
		return
	}

	jsonResponse(w, friendReq, http.StatusCreated)
}

func (h *Handler) GetPendingRequests(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	requests, err := db.GetPendingFriendRequests(h.db, userCtx.UserID)
	if err != nil {
		jsonError(w, "failed to get friend requests", http.StatusInternalServerError)
		return
	}

	if requests == nil {
		requests = []models.FriendRequest{}
	}

	jsonResponse(w, requests, http.StatusOK)
}

func (h *Handler) RespondToFriendRequest(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	// Extract request ID from path
	path := r.URL.Path
	parts := strings.Split(path, "/")
	if len(parts) < 4 {
		jsonError(w, "invalid request path", http.StatusBadRequest)
		return
	}
	requestID, err := strconv.ParseInt(parts[len(parts)-1], 10, 64)
	if err != nil {
		jsonError(w, "invalid request ID", http.StatusBadRequest)
		return
	}

	var req models.FriendRequestAction
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	switch req.Action {
	case "accept":
		if err := db.AcceptFriendRequest(h.db, requestID, userCtx.UserID); err != nil {
			if err == db.ErrFriendRequestNotFound {
				jsonError(w, "friend request not found", http.StatusNotFound)
				return
			}
			jsonError(w, "failed to accept friend request", http.StatusInternalServerError)
			return
		}
		jsonResponse(w, map[string]string{"message": "friend request accepted"}, http.StatusOK)

	case "deny":
		if err := db.DenyFriendRequest(h.db, requestID, userCtx.UserID); err != nil {
			if err == db.ErrFriendRequestNotFound {
				jsonError(w, "friend request not found", http.StatusNotFound)
				return
			}
			jsonError(w, "failed to deny friend request", http.StatusInternalServerError)
			return
		}
		jsonResponse(w, map[string]string{"message": "friend request denied"}, http.StatusOK)

	default:
		jsonError(w, "action must be 'accept' or 'deny'", http.StatusBadRequest)
	}
}

func (h *Handler) RemoveFriend(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	// Extract friend ID from path
	path := r.URL.Path
	parts := strings.Split(path, "/")
	if len(parts) < 3 {
		jsonError(w, "invalid request path", http.StatusBadRequest)
		return
	}
	friendID, err := strconv.ParseInt(parts[len(parts)-1], 10, 64)
	if err != nil {
		jsonError(w, "invalid friend ID", http.StatusBadRequest)
		return
	}

	if err := db.RemoveFriend(h.db, userCtx.UserID, friendID); err != nil {
		if err == db.ErrFriendshipNotFound {
			jsonError(w, "friendship not found", http.StatusNotFound)
			return
		}
		jsonError(w, "failed to remove friend", http.StatusInternalServerError)
		return
	}

	jsonResponse(w, map[string]string{"message": "friend removed"}, http.StatusOK)
}
