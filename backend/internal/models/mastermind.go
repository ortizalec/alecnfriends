package models

import "time"

type MastermindGame struct {
	ID           int64     `json:"id"`
	Player1ID    int64     `json:"player1_id"`
	Player2ID    int64     `json:"player2_id"`
	CurrentTurn  int64     `json:"current_turn"`
	Status       string    `json:"status"` // setup, active, completed
	WinnerID     *int64    `json:"winner_id,omitempty"`
	MaxGuesses   int       `json:"max_guesses"`
	NumColors    int       `json:"num_colors"`    // 4, 6, or 8 colors
	AllowRepeats bool      `json:"allow_repeats"` // whether duplicate colors allowed
	CreatedAt    time.Time `json:"created_at"`
	UpdatedAt    time.Time `json:"updated_at"`

	// Populated for responses
	Player1 *User `json:"player1,omitempty"`
	Player2 *User `json:"player2,omitempty"`
}

type MastermindSecret struct {
	ID        int64     `json:"id"`
	GameID    int64     `json:"game_id"`
	UserID    int64     `json:"user_id"`
	Code      string    `json:"-"` // JSON array stored as string
	CreatedAt time.Time `json:"created_at"`
}

type MastermindGuess struct {
	ID          int64     `json:"id"`
	GameID      int64     `json:"game_id"`
	UserID      int64     `json:"user_id"` // Guesser
	Guess       string    `json:"-"`       // JSON array stored as string
	Correct     int       `json:"correct"`
	Misplaced   int       `json:"misplaced"`
	GuessNumber int       `json:"guess_number"`
	CreatedAt   time.Time `json:"created_at"`
}

// MastermindGuessResponse is the response format with parsed guess
type MastermindGuessResponse struct {
	ID          int64     `json:"id"`
	GameID      int64     `json:"game_id"`
	UserID      int64     `json:"user_id"`
	Guess       []int     `json:"guess"`
	Correct     int       `json:"correct"`
	Misplaced   int       `json:"misplaced"`
	GuessNumber int       `json:"guess_number"`
	CreatedAt   time.Time `json:"created_at"`
}

// Request types
type CreateMastermindGameRequest struct {
	OpponentID   int64 `json:"opponent_id"`
	NumColors    int   `json:"num_colors"`    // 4, 6, or 8 (default 6)
	AllowRepeats bool  `json:"allow_repeats"` // default true
}

type SetMastermindSecretRequest struct {
	Code []int `json:"code"` // 4 color indices (0-7)
}

type MakeMastermindGuessRequest struct {
	Guess []int `json:"guess"` // 4 color indices (0-7)
}

// Response types
type MastermindGameResponse struct {
	Game          *MastermindGame           `json:"game"`
	MySecret      []int                     `json:"my_secret,omitempty"`      // Only show after game ends or for self
	OpponentSecret []int                    `json:"opponent_secret,omitempty"` // Only show after game ends
	MyGuesses     []MastermindGuessResponse `json:"my_guesses"`               // Guesses I made
	TheirGuesses  []MastermindGuessResponse `json:"their_guesses"`            // Guesses opponent made
	SecretSet     bool                      `json:"secret_set"`               // Have I set my secret?
	IsYourTurn    bool                      `json:"is_your_turn"`
	Phase         string                    `json:"phase"`                    // setup, active, completed
	Round         int                       `json:"round"`                    // Current round number
}

type MastermindGamesListResponse struct {
	YourTurn  []MastermindGame `json:"your_turn"`
	TheirTurn []MastermindGame `json:"their_turn"`
	Completed []MastermindGame `json:"completed"`
}
