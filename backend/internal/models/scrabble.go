package models

import "time"

type ScrabbleGame struct {
	ID               int64     `json:"id"`
	Player1ID        int64     `json:"player1_id"`
	Player2ID        int64     `json:"player2_id"`
	CurrentTurn      int64     `json:"current_turn"`
	Player1Score     int       `json:"player1_score"`
	Player2Score     int       `json:"player2_score"`
	Status           string    `json:"status"`
	WinnerID         *int64    `json:"winner_id,omitempty"`
	TileBag          string    `json:"-"`
	BoardState       string    `json:"-"`
	ConsecutivePasses int      `json:"consecutive_passes"`
	CreatedAt        time.Time `json:"created_at"`
	UpdatedAt        time.Time `json:"updated_at"`

	// Populated for responses
	Player1  *User    `json:"player1,omitempty"`
	Player2  *User    `json:"player2,omitempty"`
	Board    [][]Tile `json:"board,omitempty"`
}

type ScrabbleRack struct {
	ID     int64  `json:"id"`
	GameID int64  `json:"game_id"`
	UserID int64  `json:"user_id"`
	Tiles  string `json:"-"`
}

type ScrabbleMove struct {
	ID          int64     `json:"id"`
	GameID      int64     `json:"game_id"`
	UserID      int64     `json:"user_id"`
	MoveType    string    `json:"move_type"`
	TilesPlayed string    `json:"tiles_played,omitempty"`
	WordsFormed string    `json:"words_formed,omitempty"`
	Score       int       `json:"score"`
	CreatedAt   time.Time `json:"created_at"`
}

type Tile struct {
	Letter string `json:"letter"`
	Value  int    `json:"value"`
	Row    int    `json:"row,omitempty"`
	Col    int    `json:"col,omitempty"`
	IsNew  bool   `json:"is_new,omitempty"`
}

type PlacedTile struct {
	Letter string `json:"letter"`
	Row    int    `json:"row"`
	Col    int    `json:"col"`
}

// API Request/Response types
type CreateScrabbleGameRequest struct {
	OpponentID int64 `json:"opponent_id"`
}

type PlayMoveRequest struct {
	Tiles []PlacedTile `json:"tiles"`
}

type ExchangeTilesRequest struct {
	Tiles []string `json:"tiles"`
}

type ScrabbleGameResponse struct {
	Game           *ScrabbleGame `json:"game"`
	Rack           []Tile        `json:"rack"`
	IsYourTurn     bool          `json:"is_your_turn"`
	TilesRemaining int           `json:"tiles_remaining"`
	LastMove       *ScrabbleMove `json:"last_move,omitempty"`
}

type PreviewMoveResponse struct {
	Valid       bool     `json:"valid"`
	Score       int      `json:"score"`
	Words       []string `json:"words"`
	Error       string   `json:"error,omitempty"`
}

type ScrabbleGamesListResponse struct {
	YourTurn   []ScrabbleGame `json:"your_turn"`
	TheirTurn  []ScrabbleGame `json:"their_turn"`
	Completed  []ScrabbleGame `json:"completed"`
}
