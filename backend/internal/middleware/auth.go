package middleware

import (
	"context"
	"net/http"
	"strings"

	"altech/internal/auth"
)

type contextKey string

const UserContextKey contextKey = "user"

type UserContext struct {
	UserID   int64
	Username string
}

func Auth(jwtSecret string, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		authHeader := r.Header.Get("Authorization")
		if authHeader == "" {
			http.Error(w, `{"error":"missing authorization header"}`, http.StatusUnauthorized)
			return
		}

		parts := strings.Split(authHeader, " ")
		if len(parts) != 2 || parts[0] != "Bearer" {
			http.Error(w, `{"error":"invalid authorization header format"}`, http.StatusUnauthorized)
			return
		}

		claims, err := auth.ValidateAccessToken(parts[1], jwtSecret)
		if err != nil {
			http.Error(w, `{"error":"invalid or expired token"}`, http.StatusUnauthorized)
			return
		}

		ctx := context.WithValue(r.Context(), UserContextKey, &UserContext{
			UserID:   claims.UserID,
			Username: claims.Username,
		})

		next(w, r.WithContext(ctx))
	}
}

func GetUser(r *http.Request) *UserContext {
	if user, ok := r.Context().Value(UserContextKey).(*UserContext); ok {
		return user
	}
	return nil
}
