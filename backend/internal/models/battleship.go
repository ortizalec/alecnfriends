package models

import "time"

type BattleshipGame struct {
	ID           int64     `json:"id"`
	Player1ID    int64     `json:"player1_id"`
	Player2ID    int64     `json:"player2_id"`
	CurrentTurn  int64     `json:"current_turn"`
	Status       string    `json:"status"` // setup, active, completed
	WinnerID     *int64    `json:"winner_id,omitempty"`
	CreatedAt    time.Time `json:"created_at"`
	UpdatedAt    time.Time `json:"updated_at"`

	// Populated for responses
	Player1 *User `json:"player1,omitempty"`
	Player2 *User `json:"player2,omitempty"`
}

type BattleshipBoard struct {
	ID         int64  `json:"id"`
	GameID     int64  `json:"game_id"`
	UserID     int64  `json:"user_id"`
	Ships      string `json:"-"` // JSON array of ship placements
	Shots      string `json:"-"` // JSON array of shots received
	ShipsReady bool   `json:"ships_ready"`
}

type Ship struct {
	Type      string `json:"type"` // carrier, battleship, cruiser, submarine, destroyer
	StartRow  int    `json:"start_row"`
	StartCol  int    `json:"start_col"`
	Horizontal bool  `json:"horizontal"`
	Size      int    `json:"size"`
	Hits      int    `json:"hits"`
}

type Shot struct {
	Row int  `json:"row"`
	Col int  `json:"col"`
	Hit bool `json:"hit"`
}

type Cell struct {
	Row    int    `json:"row"`
	Col    int    `json:"col"`
	Status string `json:"status"` // empty, ship, hit, miss
}

// API Request/Response types
type CreateBattleshipGameRequest struct {
	OpponentID int64 `json:"opponent_id"`
}

type PlaceShipsRequest struct {
	Ships []Ship `json:"ships"`
}

type FireShotRequest struct {
	Row int `json:"row"`
	Col int `json:"col"`
}

type BattleshipGameResponse struct {
	Game                *BattleshipGame `json:"game"`
	MyBoard             [][]string      `json:"my_board"`              // Full view of own board
	EnemyBoard          [][]string      `json:"enemy_board"`           // Only shows hits/misses
	MyShips             []Ship          `json:"my_ships"`
	IsYourTurn          bool            `json:"is_your_turn"`
	ShipsReady          bool            `json:"ships_ready"`
	Phase               string          `json:"phase"`                  // setup, active, completed
	EnemyShipsRemaining int             `json:"enemy_ships_remaining"`  // How many enemy ships are still afloat
}

type BattleshipGamesListResponse struct {
	YourTurn  []BattleshipGame `json:"your_turn"`
	TheirTurn []BattleshipGame `json:"their_turn"`
	Completed []BattleshipGame `json:"completed"`
}

type FireShotResponse struct {
	Hit        bool   `json:"hit"`
	Sunk       bool   `json:"sunk"`
	ShipType   string `json:"ship_type,omitempty"`
	GameOver   bool   `json:"game_over"`
	Winner     string `json:"winner,omitempty"`
}
