package battleship

import (
	"encoding/json"
	"errors"

	"altech/internal/models"
)

const BoardSize = 10

var ShipSizes = map[string]int{
	"carrier":    5,
	"battleship": 4,
	"cruiser":    3,
	"submarine":  3,
	"destroyer":  2,
}

var RequiredShips = []string{"carrier", "battleship", "cruiser", "submarine", "destroyer"}

// CreateEmptyBoard creates a 10x10 empty board
func CreateEmptyBoard() [][]string {
	board := make([][]string, BoardSize)
	for i := range board {
		board[i] = make([]string, BoardSize)
		for j := range board[i] {
			board[i][j] = "empty"
		}
	}
	return board
}

// ValidateShipPlacement checks if ships are placed correctly
func ValidateShipPlacement(ships []models.Ship) error {
	if len(ships) != len(RequiredShips) {
		return errors.New("must place exactly 5 ships")
	}

	// Check all required ship types
	placedTypes := make(map[string]bool)
	for _, ship := range ships {
		if placedTypes[ship.Type] {
			return errors.New("duplicate ship type: " + ship.Type)
		}
		placedTypes[ship.Type] = true

		expectedSize, ok := ShipSizes[ship.Type]
		if !ok {
			return errors.New("invalid ship type: " + ship.Type)
		}
		if ship.Size != expectedSize {
			return errors.New("invalid ship size for " + ship.Type)
		}
	}

	for _, requiredType := range RequiredShips {
		if !placedTypes[requiredType] {
			return errors.New("missing ship: " + requiredType)
		}
	}

	// Check bounds and overlaps
	occupied := make(map[string]bool)
	for _, ship := range ships {
		cells := GetShipCells(ship)
		for _, cell := range cells {
			if cell.Row < 0 || cell.Row >= BoardSize || cell.Col < 0 || cell.Col >= BoardSize {
				return errors.New("ship out of bounds")
			}
			key := cellKey(cell.Row, cell.Col)
			if occupied[key] {
				return errors.New("ships overlap")
			}
			occupied[key] = true
		}
	}

	return nil
}

// GetShipCells returns all cells occupied by a ship
func GetShipCells(ship models.Ship) []models.Cell {
	cells := make([]models.Cell, ship.Size)
	for i := 0; i < ship.Size; i++ {
		if ship.Horizontal {
			cells[i] = models.Cell{Row: ship.StartRow, Col: ship.StartCol + i}
		} else {
			cells[i] = models.Cell{Row: ship.StartRow + i, Col: ship.StartCol}
		}
	}
	return cells
}

// ProcessShot processes a shot and returns the result
func ProcessShot(ships []models.Ship, shots []models.Shot, row, col int) (hit bool, sunk bool, shipType string, err error) {
	if row < 0 || row >= BoardSize || col < 0 || col >= BoardSize {
		return false, false, "", errors.New("shot out of bounds")
	}

	// Check if already shot
	for _, shot := range shots {
		if shot.Row == row && shot.Col == col {
			return false, false, "", errors.New("already shot at this location")
		}
	}

	// Check if hit
	for i, ship := range ships {
		cells := GetShipCells(ship)
		for _, cell := range cells {
			if cell.Row == row && cell.Col == col {
				ships[i].Hits++
				sunk = ships[i].Hits >= ship.Size
				return true, sunk, ship.Type, nil
			}
		}
	}

	return false, false, "", nil
}

// CheckAllShipsSunk returns true if all ships are sunk
func CheckAllShipsSunk(ships []models.Ship) bool {
	for _, ship := range ships {
		if ship.Hits < ship.Size {
			return false
		}
	}
	return true
}

// BuildMyBoard creates the full view of own board (shows ships)
func BuildMyBoard(ships []models.Ship, shots []models.Shot) [][]string {
	board := CreateEmptyBoard()

	// Place ships
	for _, ship := range ships {
		cells := GetShipCells(ship)
		for _, cell := range cells {
			board[cell.Row][cell.Col] = "ship"
		}
	}

	// Apply shots
	for _, shot := range shots {
		if shot.Hit {
			board[shot.Row][shot.Col] = "hit"
		} else {
			board[shot.Row][shot.Col] = "miss"
		}
	}

	return board
}

// BuildEnemyBoard creates the limited view of enemy board (only hits/misses)
func BuildEnemyBoard(shots []models.Shot) [][]string {
	board := CreateEmptyBoard()

	for _, shot := range shots {
		if shot.Hit {
			board[shot.Row][shot.Col] = "hit"
		} else {
			board[shot.Row][shot.Col] = "miss"
		}
	}

	return board
}

// JSON helpers
func ShipsToJSON(ships []models.Ship) (string, error) {
	data, err := json.Marshal(ships)
	return string(data), err
}

func ShipsFromJSON(data string) ([]models.Ship, error) {
	var ships []models.Ship
	if data == "" || data == "[]" {
		return ships, nil
	}
	err := json.Unmarshal([]byte(data), &ships)
	return ships, err
}

func ShotsToJSON(shots []models.Shot) (string, error) {
	data, err := json.Marshal(shots)
	return string(data), err
}

func ShotsFromJSON(data string) ([]models.Shot, error) {
	var shots []models.Shot
	if data == "" || data == "[]" {
		return shots, nil
	}
	err := json.Unmarshal([]byte(data), &shots)
	return shots, err
}

func cellKey(row, col int) string {
	return string(rune('A'+row)) + string(rune('0'+col))
}
