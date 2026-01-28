package models

import "time"

type MemoryGame struct {
	ID           int64     `json:"id"`
	Player1ID    int64     `json:"player1_id"`
	Player2ID    int64     `json:"player2_id"`
	CurrentTurn  int64     `json:"current_turn"`
	Status       string    `json:"status"` // active, completed
	WinnerID     *int64    `json:"winner_id,omitempty"`
	BoardSize    string    `json:"board_size"` // "4x5", "6x6", "8x8"
	Board        string    `json:"-"`          // JSON 2D array of tile IDs (server only)
	Matched      string    `json:"-"`          // JSON 2D array of matched booleans
	Player1Score int       `json:"player1_score"`
	Player2Score int       `json:"player2_score"`
	CreatedAt    time.Time `json:"created_at"`
	UpdatedAt    time.Time `json:"updated_at"`

	// Populated for responses
	Player1 *User `json:"player1,omitempty"`
	Player2 *User `json:"player2,omitempty"`
}

type MemoryMove struct {
	ID        int64     `json:"id"`
	GameID    int64     `json:"game_id"`
	UserID    int64     `json:"user_id"`
	Row1      int       `json:"row1"`
	Col1      int       `json:"col1"`
	Row2      int       `json:"row2"`
	Col2      int       `json:"col2"`
	Tile1     int       `json:"tile1"`
	Tile2     int       `json:"tile2"`
	Matched   bool      `json:"matched"`
	CreatedAt time.Time `json:"created_at"`
}

// Request types
type CreateMemoryGameRequest struct {
	OpponentID int64  `json:"opponent_id"`
	BoardSize  string `json:"board_size"` // "4x5", "6x6", "8x8"
}

type RevealTilesRequest struct {
	Row1 int `json:"row1"`
	Col1 int `json:"col1"`
	Row2 int `json:"row2"`
	Col2 int `json:"col2"`
}

type RevealTilesResponse struct {
	Tile1     int  `json:"tile1"`
	Tile2     int  `json:"tile2"`
	Matched   bool `json:"matched"`
	ExtraTurn bool `json:"extra_turn"`
	GameOver  bool `json:"game_over"`
}

type MemoryGameResponse struct {
	Game         *MemoryGame  `json:"game"`
	Board        [][]int      `json:"board"`
	FullBoard    [][]int      `json:"full_board,omitempty"` // only sent to current turn player
	IsYourTurn   bool         `json:"is_your_turn"`
	Moves        []MemoryMove `json:"moves"`
	TotalPairs   int          `json:"total_pairs"`
	MatchedCount int          `json:"matched_count"`
}

type MemoryGamesListResponse struct {
	YourTurn  []MemoryGame `json:"your_turn"`
	TheirTurn []MemoryGame `json:"their_turn"`
	Completed []MemoryGame `json:"completed"`
}
