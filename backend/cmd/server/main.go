package main

import (
	"log"
	"net/http"
	"os"

	"altech/internal/db"
	"altech/internal/handlers"
	"altech/internal/middleware"
)

func main() {
	// Load configuration
	port := os.Getenv("PORT")
	if port == "" {
		port = "8080"
	}

	dbPath := os.Getenv("DATABASE_PATH")
	if dbPath == "" {
		dbPath = "./data/alecnfriends.db"
	}

	jwtSecret := os.Getenv("JWT_SECRET")
	if jwtSecret == "" {
		log.Fatal("JWT_SECRET environment variable is required")
	}

	frontendURL := os.Getenv("FRONTEND_URL")
	if frontendURL == "" {
		frontendURL = "http://localhost:5173"
	}

	// Initialize database
	database, err := db.Initialize(dbPath)
	if err != nil {
		log.Fatalf("Failed to initialize database: %v", err)
	}
	defer database.Close()

	// Create handler with dependencies
	h := handlers.New(database, jwtSecret)

	// Set up routes
	mux := http.NewServeMux()

	// Public routes
	mux.HandleFunc("POST /api/register", h.Register)
	mux.HandleFunc("POST /api/login", h.Login)
	mux.HandleFunc("POST /api/refresh", h.Refresh)

	// Protected routes
	mux.HandleFunc("GET /api/me", middleware.Auth(jwtSecret, h.Me))

	// Friends routes
	mux.HandleFunc("GET /api/friends", middleware.Auth(jwtSecret, h.GetFriends))
	mux.HandleFunc("POST /api/friends/request", middleware.Auth(jwtSecret, h.SendFriendRequest))
	mux.HandleFunc("GET /api/friends/requests", middleware.Auth(jwtSecret, h.GetPendingRequests))
	mux.HandleFunc("POST /api/friends/requests/{id}", middleware.Auth(jwtSecret, h.RespondToFriendRequest))
	mux.HandleFunc("DELETE /api/friends/{id}", middleware.Auth(jwtSecret, h.RemoveFriend))

	// Scrabble routes
	mux.HandleFunc("GET /api/scrabble/games", middleware.Auth(jwtSecret, h.GetScrabbleGames))
	mux.HandleFunc("POST /api/scrabble/games", middleware.Auth(jwtSecret, h.CreateScrabbleGame))
	mux.HandleFunc("GET /api/scrabble/games/{id}", middleware.Auth(jwtSecret, h.GetScrabbleGame))
	mux.HandleFunc("POST /api/scrabble/games/{id}/play", middleware.Auth(jwtSecret, h.PlayScrabbleMove))
	mux.HandleFunc("POST /api/scrabble/games/{id}/preview", middleware.Auth(jwtSecret, h.PreviewScrabbleMove))
	mux.HandleFunc("POST /api/scrabble/games/{id}/pass", middleware.Auth(jwtSecret, h.PassScrabbleTurn))
	mux.HandleFunc("POST /api/scrabble/games/{id}/exchange", middleware.Auth(jwtSecret, h.ExchangeScrabbleTiles))
	mux.HandleFunc("POST /api/scrabble/games/{id}/resign", middleware.Auth(jwtSecret, h.ResignScrabbleGame))
	mux.HandleFunc("GET /api/scrabble/games/{id}/bag", middleware.Auth(jwtSecret, h.GetTileBag))
	mux.HandleFunc("GET /api/scrabble/games/{id}/history", middleware.Auth(jwtSecret, h.GetGameHistory))

	// Battleship routes
	mux.HandleFunc("GET /api/battleship/games", middleware.Auth(jwtSecret, h.GetBattleshipGames))
	mux.HandleFunc("POST /api/battleship/games", middleware.Auth(jwtSecret, h.CreateBattleshipGame))
	mux.HandleFunc("GET /api/battleship/games/{id}", middleware.Auth(jwtSecret, h.GetBattleshipGame))
	mux.HandleFunc("POST /api/battleship/games/{id}/ships", middleware.Auth(jwtSecret, h.PlaceBattleshipShips))
	mux.HandleFunc("POST /api/battleship/games/{id}/fire", middleware.Auth(jwtSecret, h.FireBattleshipShot))
	mux.HandleFunc("POST /api/battleship/games/{id}/resign", middleware.Auth(jwtSecret, h.ResignBattleshipGame))

	// Mastermind routes
	mux.HandleFunc("GET /api/mastermind/games", middleware.Auth(jwtSecret, h.GetMastermindGames))
	mux.HandleFunc("POST /api/mastermind/games", middleware.Auth(jwtSecret, h.CreateMastermindGame))
	mux.HandleFunc("GET /api/mastermind/games/{id}", middleware.Auth(jwtSecret, h.GetMastermindGame))
	mux.HandleFunc("POST /api/mastermind/games/{id}/secret", middleware.Auth(jwtSecret, h.SetMastermindSecret))
	mux.HandleFunc("POST /api/mastermind/games/{id}/guess", middleware.Auth(jwtSecret, h.MakeMastermindGuess))
	mux.HandleFunc("POST /api/mastermind/games/{id}/resign", middleware.Auth(jwtSecret, h.ResignMastermindGame))

	// Memory routes
	mux.HandleFunc("GET /api/memory/games", middleware.Auth(jwtSecret, h.GetMemoryGames))
	mux.HandleFunc("POST /api/memory/games", middleware.Auth(jwtSecret, h.CreateMemoryGame))
	mux.HandleFunc("GET /api/memory/games/{id}", middleware.Auth(jwtSecret, h.GetMemoryGame))
	mux.HandleFunc("POST /api/memory/games/{id}/reveal", middleware.Auth(jwtSecret, h.RevealTiles))
	mux.HandleFunc("POST /api/memory/games/{id}/resign", middleware.Auth(jwtSecret, h.ResignMemoryGame))

	// Health check
	mux.HandleFunc("GET /api/health", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		w.Write([]byte(`{"status":"ok"}`))
	})

	// Apply global middleware
	handler := middleware.Logger(middleware.CORS(frontendURL, mux))

	log.Printf("Server starting on port %s", port)
	if err := http.ListenAndServe(":"+port, handler); err != nil {
		log.Fatalf("Server failed: %v", err)
	}
}
