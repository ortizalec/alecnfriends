package scrabble

import (
	"encoding/json"
	"math/rand"
	"time"

	"altech/internal/models"
)

const BoardSize = 15

// Bonus square types
const (
	Normal       = 0
	DoubleLetter = 1
	TripleLetter = 2
	DoubleWord   = 3
	TripleWord   = 4
	Center       = 5
)

// Letter values
var LetterValues = map[string]int{
	"A": 1, "B": 3, "C": 3, "D": 2, "E": 1, "F": 4, "G": 2, "H": 4,
	"I": 1, "J": 8, "K": 5, "L": 1, "M": 3, "N": 1, "O": 1, "P": 3,
	"Q": 10, "R": 1, "S": 1, "T": 1, "U": 1, "V": 4, "W": 4, "X": 8,
	"Y": 4, "Z": 10, " ": 0, // blank tile
}

// Initial tile distribution
var TileDistribution = map[string]int{
	"A": 9, "B": 2, "C": 2, "D": 4, "E": 12, "F": 2, "G": 3, "H": 2,
	"I": 9, "J": 1, "K": 1, "L": 4, "M": 2, "N": 6, "O": 8, "P": 2,
	"Q": 1, "R": 6, "S": 4, "T": 6, "U": 4, "V": 2, "W": 2, "X": 1,
	"Y": 2, "Z": 1, " ": 2, // 2 blank tiles
}

// BonusSquares defines the bonus type for each square
// Uses symmetry - only need to define one quadrant
var BonusSquares = [BoardSize][BoardSize]int{
	{4, 0, 0, 1, 0, 0, 0, 4, 0, 0, 0, 1, 0, 0, 4},
	{0, 3, 0, 0, 0, 2, 0, 0, 0, 2, 0, 0, 0, 3, 0},
	{0, 0, 3, 0, 0, 0, 1, 0, 1, 0, 0, 0, 3, 0, 0},
	{1, 0, 0, 3, 0, 0, 0, 1, 0, 0, 0, 3, 0, 0, 1},
	{0, 0, 0, 0, 3, 0, 0, 0, 0, 0, 3, 0, 0, 0, 0},
	{0, 2, 0, 0, 0, 2, 0, 0, 0, 2, 0, 0, 0, 2, 0},
	{0, 0, 1, 0, 0, 0, 1, 0, 1, 0, 0, 0, 1, 0, 0},
	{4, 0, 0, 1, 0, 0, 0, 5, 0, 0, 0, 1, 0, 0, 4},
	{0, 0, 1, 0, 0, 0, 1, 0, 1, 0, 0, 0, 1, 0, 0},
	{0, 2, 0, 0, 0, 2, 0, 0, 0, 2, 0, 0, 0, 2, 0},
	{0, 0, 0, 0, 3, 0, 0, 0, 0, 0, 3, 0, 0, 0, 0},
	{1, 0, 0, 3, 0, 0, 0, 1, 0, 0, 0, 3, 0, 0, 1},
	{0, 0, 3, 0, 0, 0, 1, 0, 1, 0, 0, 0, 3, 0, 0},
	{0, 3, 0, 0, 0, 2, 0, 0, 0, 2, 0, 0, 0, 3, 0},
	{4, 0, 0, 1, 0, 0, 0, 4, 0, 0, 0, 1, 0, 0, 4},
}

func init() {
	rand.Seed(time.Now().UnixNano())
}

// CreateTileBag creates a new shuffled tile bag
func CreateTileBag() []models.Tile {
	var bag []models.Tile
	for letter, count := range TileDistribution {
		for i := 0; i < count; i++ {
			bag = append(bag, models.Tile{
				Letter: letter,
				Value:  LetterValues[letter],
			})
		}
	}
	// Shuffle
	rand.Shuffle(len(bag), func(i, j int) {
		bag[i], bag[j] = bag[j], bag[i]
	})
	return bag
}

// DrawTiles draws n tiles from the bag, returns drawn tiles and remaining bag
func DrawTiles(bag []models.Tile, n int) (drawn []models.Tile, remaining []models.Tile) {
	if n > len(bag) {
		n = len(bag)
	}
	return bag[:n], bag[n:]
}

// CreateEmptyBoard creates a 15x15 empty board
func CreateEmptyBoard() [][]models.Tile {
	board := make([][]models.Tile, BoardSize)
	for i := range board {
		board[i] = make([]models.Tile, BoardSize)
	}
	return board
}

// TileBagToJSON converts tile bag to JSON string
func TileBagToJSON(bag []models.Tile) (string, error) {
	data, err := json.Marshal(bag)
	return string(data), err
}

// TileBagFromJSON parses tile bag from JSON string
func TileBagFromJSON(data string) ([]models.Tile, error) {
	var bag []models.Tile
	err := json.Unmarshal([]byte(data), &bag)
	return bag, err
}

// BoardToJSON converts board to JSON string
func BoardToJSON(board [][]models.Tile) (string, error) {
	data, err := json.Marshal(board)
	return string(data), err
}

// BoardFromJSON parses board from JSON string
func BoardFromJSON(data string) ([][]models.Tile, error) {
	var board [][]models.Tile
	err := json.Unmarshal([]byte(data), &board)
	if err != nil {
		return nil, err
	}
	// Ensure proper dimensions
	if len(board) != BoardSize {
		board = CreateEmptyBoard()
	}
	return board, nil
}

// RackToJSON converts rack tiles to JSON string
func RackToJSON(rack []models.Tile) (string, error) {
	data, err := json.Marshal(rack)
	return string(data), err
}

// RackFromJSON parses rack from JSON string
func RackFromJSON(data string) ([]models.Tile, error) {
	var rack []models.Tile
	err := json.Unmarshal([]byte(data), &rack)
	return rack, err
}

// GetBonusType returns the bonus type for a square
func GetBonusType(row, col int) int {
	if row < 0 || row >= BoardSize || col < 0 || col >= BoardSize {
		return Normal
	}
	return BonusSquares[row][col]
}

// IsBoardEmpty checks if the board has any tiles
func IsBoardEmpty(board [][]models.Tile) bool {
	for r := 0; r < BoardSize; r++ {
		for c := 0; c < BoardSize; c++ {
			if board[r][c].Letter != "" {
				return false
			}
		}
	}
	return true
}
