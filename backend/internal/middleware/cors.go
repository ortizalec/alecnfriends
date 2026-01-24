package middleware

import (
	"net/http"
	"strings"
)

func CORS(allowedOrigins string, next http.Handler) http.Handler {
	// Parse comma-separated origins
	origins := make(map[string]bool)
	for _, o := range strings.Split(allowedOrigins, ",") {
		origins[strings.TrimSpace(o)] = true
	}

	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		origin := r.Header.Get("Origin")

		// Check if origin is allowed
		if origins[origin] || origins["*"] {
			w.Header().Set("Access-Control-Allow-Origin", origin)
		} else if len(origins) == 1 {
			// Single origin mode
			for o := range origins {
				w.Header().Set("Access-Control-Allow-Origin", o)
			}
		}

		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")
		w.Header().Set("Access-Control-Allow-Credentials", "true")

		if r.Method == "OPTIONS" {
			w.WriteHeader(http.StatusOK)
			return
		}

		next.ServeHTTP(w, r)
	})
}
