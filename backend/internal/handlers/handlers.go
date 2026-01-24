package handlers

import (
	"database/sql"
	"encoding/json"
	"net/http"
	"strings"
	"time"

	"altech/internal/auth"
	"altech/internal/db"
	"altech/internal/middleware"
	"altech/internal/models"

	"golang.org/x/crypto/bcrypt"
)

type Handler struct {
	db        *sql.DB
	jwtSecret string
}

func New(database *sql.DB, jwtSecret string) *Handler {
	return &Handler{
		db:        database,
		jwtSecret: jwtSecret,
	}
}

func (h *Handler) Register(w http.ResponseWriter, r *http.Request) {
	var req models.RegisterRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	// Validate input
	req.Username = strings.TrimSpace(req.Username)
	if len(req.Username) < 3 || len(req.Username) > 32 {
		jsonError(w, "username must be between 3 and 32 characters", http.StatusBadRequest)
		return
	}
	if len(req.Password) < 8 {
		jsonError(w, "password must be at least 8 characters", http.StatusBadRequest)
		return
	}

	// Hash password
	hash, err := bcrypt.GenerateFromPassword([]byte(req.Password), bcrypt.DefaultCost)
	if err != nil {
		jsonError(w, "failed to process password", http.StatusInternalServerError)
		return
	}

	// Create user
	user, err := db.CreateUser(h.db, req.Username, string(hash))
	if err == db.ErrUserExists {
		jsonError(w, "username already taken", http.StatusConflict)
		return
	}
	if err != nil {
		jsonError(w, "failed to create user", http.StatusInternalServerError)
		return
	}

	// Generate tokens
	accessToken, err := auth.GenerateAccessToken(user.ID, user.Username, h.jwtSecret)
	if err != nil {
		jsonError(w, "failed to generate access token", http.StatusInternalServerError)
		return
	}

	refreshToken, err := auth.GenerateRefreshToken()
	if err != nil {
		jsonError(w, "failed to generate refresh token", http.StatusInternalServerError)
		return
	}

	// Store refresh token
	tokenHash := auth.HashToken(refreshToken)
	expiresAt := time.Now().Add(auth.RefreshTokenDuration)
	if err := db.StoreRefreshToken(h.db, user.ID, tokenHash, expiresAt); err != nil {
		jsonError(w, "failed to store refresh token", http.StatusInternalServerError)
		return
	}

	jsonResponse(w, models.AuthResponse{
		AccessToken:  accessToken,
		RefreshToken: refreshToken,
		User:         *user,
	}, http.StatusCreated)
}

func (h *Handler) Login(w http.ResponseWriter, r *http.Request) {
	var req models.LoginRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	// Get user
	user, err := db.GetUserByUsername(h.db, req.Username)
	if err == db.ErrUserNotFound {
		jsonError(w, "invalid username or password", http.StatusUnauthorized)
		return
	}
	if err != nil {
		jsonError(w, "failed to authenticate", http.StatusInternalServerError)
		return
	}

	// Verify password
	if err := bcrypt.CompareHashAndPassword([]byte(user.PasswordHash), []byte(req.Password)); err != nil {
		jsonError(w, "invalid username or password", http.StatusUnauthorized)
		return
	}

	// Generate tokens
	accessToken, err := auth.GenerateAccessToken(user.ID, user.Username, h.jwtSecret)
	if err != nil {
		jsonError(w, "failed to generate access token", http.StatusInternalServerError)
		return
	}

	refreshToken, err := auth.GenerateRefreshToken()
	if err != nil {
		jsonError(w, "failed to generate refresh token", http.StatusInternalServerError)
		return
	}

	// Store refresh token
	tokenHash := auth.HashToken(refreshToken)
	expiresAt := time.Now().Add(auth.RefreshTokenDuration)
	if err := db.StoreRefreshToken(h.db, user.ID, tokenHash, expiresAt); err != nil {
		jsonError(w, "failed to store refresh token", http.StatusInternalServerError)
		return
	}

	jsonResponse(w, models.AuthResponse{
		AccessToken:  accessToken,
		RefreshToken: refreshToken,
		User:         *user,
	}, http.StatusOK)
}

func (h *Handler) Refresh(w http.ResponseWriter, r *http.Request) {
	var req models.RefreshRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid request body", http.StatusBadRequest)
		return
	}

	// Validate refresh token
	tokenHash := auth.HashToken(req.RefreshToken)
	userID, err := db.ValidateRefreshToken(h.db, tokenHash)
	if err != nil {
		jsonError(w, "invalid or expired refresh token", http.StatusUnauthorized)
		return
	}

	// Get user
	user, err := db.GetUserByID(h.db, userID)
	if err != nil {
		jsonError(w, "user not found", http.StatusUnauthorized)
		return
	}

	// Generate new access token
	accessToken, err := auth.GenerateAccessToken(user.ID, user.Username, h.jwtSecret)
	if err != nil {
		jsonError(w, "failed to generate access token", http.StatusInternalServerError)
		return
	}

	jsonResponse(w, models.RefreshResponse{
		AccessToken: accessToken,
	}, http.StatusOK)
}

func (h *Handler) Me(w http.ResponseWriter, r *http.Request) {
	userCtx := middleware.GetUser(r)
	if userCtx == nil {
		jsonError(w, "unauthorized", http.StatusUnauthorized)
		return
	}

	user, err := db.GetUserByID(h.db, userCtx.UserID)
	if err != nil {
		jsonError(w, "user not found", http.StatusNotFound)
		return
	}

	jsonResponse(w, user, http.StatusOK)
}

func jsonResponse(w http.ResponseWriter, data interface{}, status int) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

func jsonError(w http.ResponseWriter, message string, status int) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(map[string]string{"error": message})
}
