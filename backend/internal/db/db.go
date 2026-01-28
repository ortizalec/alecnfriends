package db

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"

	_ "github.com/mattn/go-sqlite3"
)

func Initialize(dbPath string) (*sql.DB, error) {
	// Ensure directory exists
	dir := filepath.Dir(dbPath)
	if err := os.MkdirAll(dir, 0755); err != nil {
		return nil, fmt.Errorf("failed to create database directory: %w", err)
	}

	db, err := sql.Open("sqlite3", dbPath+"?_foreign_keys=on")
	if err != nil {
		return nil, fmt.Errorf("failed to open database: %w", err)
	}

	if err := db.Ping(); err != nil {
		return nil, fmt.Errorf("failed to ping database: %w", err)
	}

	if err := migrate(db); err != nil {
		return nil, fmt.Errorf("failed to run migrations: %w", err)
	}

	return db, nil
}

func migrate(db *sql.DB) error {
	migrations := []string{
		`CREATE TABLE IF NOT EXISTS users (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			username TEXT UNIQUE NOT NULL,
			password_hash TEXT NOT NULL,
			friend_code TEXT UNIQUE,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
		)`,
		`CREATE TABLE IF NOT EXISTS refresh_tokens (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL,
			token_hash TEXT UNIQUE NOT NULL,
			expires_at DATETIME NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
		)`,
		`CREATE INDEX IF NOT EXISTS idx_refresh_tokens_user_id ON refresh_tokens(user_id)`,
		`CREATE INDEX IF NOT EXISTS idx_refresh_tokens_token_hash ON refresh_tokens(token_hash)`,
		`CREATE TABLE IF NOT EXISTS friend_requests (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			sender_id INTEGER NOT NULL,
			receiver_id INTEGER NOT NULL,
			status TEXT NOT NULL DEFAULT 'pending',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
			FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
			UNIQUE(sender_id, receiver_id)
		)`,
		`CREATE INDEX IF NOT EXISTS idx_friend_requests_receiver ON friend_requests(receiver_id, status)`,
		`CREATE TABLE IF NOT EXISTS friendships (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL,
			friend_id INTEGER NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
			FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
			UNIQUE(user_id, friend_id)
		)`,
		`CREATE INDEX IF NOT EXISTS idx_friendships_user ON friendships(user_id)`,
		`CREATE INDEX IF NOT EXISTS idx_users_friend_code ON users(friend_code)`,
		// Scrabble game tables
		`CREATE TABLE IF NOT EXISTS scrabble_games (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			player1_id INTEGER NOT NULL,
			player2_id INTEGER NOT NULL,
			current_turn INTEGER NOT NULL,
			player1_score INTEGER DEFAULT 0,
			player2_score INTEGER DEFAULT 0,
			status TEXT NOT NULL DEFAULT 'active',
			winner_id INTEGER,
			tile_bag TEXT NOT NULL,
			board_state TEXT NOT NULL,
			consecutive_passes INTEGER DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE,
			FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE CASCADE
		)`,
		`CREATE INDEX IF NOT EXISTS idx_scrabble_games_player1 ON scrabble_games(player1_id)`,
		`CREATE INDEX IF NOT EXISTS idx_scrabble_games_player2 ON scrabble_games(player2_id)`,
		`CREATE TABLE IF NOT EXISTS scrabble_racks (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			game_id INTEGER NOT NULL,
			user_id INTEGER NOT NULL,
			tiles TEXT NOT NULL,
			FOREIGN KEY (game_id) REFERENCES scrabble_games(id) ON DELETE CASCADE,
			UNIQUE(game_id, user_id)
		)`,
		`CREATE TABLE IF NOT EXISTS scrabble_moves (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			game_id INTEGER NOT NULL,
			user_id INTEGER NOT NULL,
			move_type TEXT NOT NULL,
			tiles_played TEXT,
			words_formed TEXT,
			score INTEGER DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (game_id) REFERENCES scrabble_games(id) ON DELETE CASCADE
		)`,
		`CREATE INDEX IF NOT EXISTS idx_scrabble_moves_game ON scrabble_moves(game_id)`,
		// Battleship game tables
		`CREATE TABLE IF NOT EXISTS battleship_games (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			player1_id INTEGER NOT NULL,
			player2_id INTEGER NOT NULL,
			current_turn INTEGER NOT NULL,
			status TEXT NOT NULL DEFAULT 'setup',
			winner_id INTEGER,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE,
			FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE CASCADE
		)`,
		`CREATE INDEX IF NOT EXISTS idx_battleship_games_player1 ON battleship_games(player1_id)`,
		`CREATE INDEX IF NOT EXISTS idx_battleship_games_player2 ON battleship_games(player2_id)`,
		`CREATE TABLE IF NOT EXISTS battleship_boards (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			game_id INTEGER NOT NULL,
			user_id INTEGER NOT NULL,
			ships TEXT NOT NULL DEFAULT '[]',
			shots TEXT NOT NULL DEFAULT '[]',
			ships_ready INTEGER DEFAULT 0,
			FOREIGN KEY (game_id) REFERENCES battleship_games(id) ON DELETE CASCADE,
			UNIQUE(game_id, user_id)
		)`,
		`CREATE INDEX IF NOT EXISTS idx_battleship_boards_game ON battleship_boards(game_id)`,
		// Mastermind game tables
		`CREATE TABLE IF NOT EXISTS mastermind_games (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			player1_id INTEGER NOT NULL,
			player2_id INTEGER NOT NULL,
			current_turn INTEGER NOT NULL,
			status TEXT NOT NULL DEFAULT 'setup',
			winner_id INTEGER,
			max_guesses INTEGER DEFAULT 10,
			num_colors INTEGER DEFAULT 6,
			allow_repeats INTEGER DEFAULT 1,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE,
			FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE CASCADE
		)`,
		`CREATE INDEX IF NOT EXISTS idx_mastermind_games_player1 ON mastermind_games(player1_id)`,
		`CREATE INDEX IF NOT EXISTS idx_mastermind_games_player2 ON mastermind_games(player2_id)`,
		`CREATE TABLE IF NOT EXISTS mastermind_secrets (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			game_id INTEGER NOT NULL,
			user_id INTEGER NOT NULL,
			code TEXT NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (game_id) REFERENCES mastermind_games(id) ON DELETE CASCADE,
			FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
			UNIQUE(game_id, user_id)
		)`,
		`CREATE INDEX IF NOT EXISTS idx_mastermind_secrets_game ON mastermind_secrets(game_id)`,
		`CREATE TABLE IF NOT EXISTS mastermind_guesses (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			game_id INTEGER NOT NULL,
			user_id INTEGER NOT NULL,
			guess TEXT NOT NULL,
			correct INTEGER NOT NULL,
			misplaced INTEGER NOT NULL,
			guess_number INTEGER NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (game_id) REFERENCES mastermind_games(id) ON DELETE CASCADE,
			FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
		)`,
		`CREATE INDEX IF NOT EXISTS idx_mastermind_guesses_game ON mastermind_guesses(game_id)`,
		// Memory game tables
		`CREATE TABLE IF NOT EXISTS memory_games (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			player1_id INTEGER NOT NULL,
			player2_id INTEGER NOT NULL,
			current_turn INTEGER NOT NULL,
			status TEXT NOT NULL DEFAULT 'active',
			winner_id INTEGER,
			board_size TEXT NOT NULL DEFAULT '4x5',
			board TEXT NOT NULL,
			matched TEXT NOT NULL DEFAULT '[]',
			player1_score INTEGER DEFAULT 0,
			player2_score INTEGER DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE,
			FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE CASCADE
		)`,
		`CREATE INDEX IF NOT EXISTS idx_memory_games_player1 ON memory_games(player1_id)`,
		`CREATE INDEX IF NOT EXISTS idx_memory_games_player2 ON memory_games(player2_id)`,
		`CREATE TABLE IF NOT EXISTS memory_moves (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			game_id INTEGER NOT NULL,
			user_id INTEGER NOT NULL,
			row1 INTEGER NOT NULL,
			col1 INTEGER NOT NULL,
			row2 INTEGER NOT NULL,
			col2 INTEGER NOT NULL,
			tile1 INTEGER NOT NULL,
			tile2 INTEGER NOT NULL,
			matched INTEGER NOT NULL DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (game_id) REFERENCES memory_games(id) ON DELETE CASCADE
		)`,
		`CREATE INDEX IF NOT EXISTS idx_memory_moves_game ON memory_moves(game_id)`,
	}

	for _, m := range migrations {
		if _, err := db.Exec(m); err != nil {
			return fmt.Errorf("migration failed: %w", err)
		}
	}

	// Optional migrations that may fail if columns already exist
	optionalMigrations := []string{
		`ALTER TABLE mastermind_games ADD COLUMN num_colors INTEGER DEFAULT 6`,
		`ALTER TABLE mastermind_games ADD COLUMN allow_repeats INTEGER DEFAULT 1`,
	}
	for _, m := range optionalMigrations {
		db.Exec(m) // Ignore errors - column may already exist
	}

	return nil
}
