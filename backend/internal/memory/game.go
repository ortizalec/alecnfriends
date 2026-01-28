package memory

import (
	"encoding/json"
	"errors"
	"math/rand"
)

// ParseBoardSize validates and returns rows, cols for a board size string.
func ParseBoardSize(size string) (int, int, error) {
	switch size {
	case "4x5":
		return 4, 5, nil
	case "6x6":
		return 6, 6, nil
	case "8x8":
		return 8, 8, nil
	default:
		return 0, 0, errors.New("invalid board size: must be 4x5, 6x6, or 8x8")
	}
}

// GenerateBoard creates a shuffled board of tile pairs.
// Each tile ID represents a unique shape+color combination.
// The board contains (rows*cols/2) pairs, each appearing exactly twice.
func GenerateBoard(rows, cols int) [][]int {
	totalTiles := rows * cols
	numPairs := totalTiles / 2

	// Create pairs: each pair ID appears twice
	tiles := make([]int, totalTiles)
	for i := 0; i < numPairs; i++ {
		tiles[i*2] = i
		tiles[i*2+1] = i
	}

	// Shuffle
	rand.Shuffle(len(tiles), func(i, j int) {
		tiles[i], tiles[j] = tiles[j], tiles[i]
	})

	// Lay into grid
	board := make([][]int, rows)
	for r := 0; r < rows; r++ {
		board[r] = make([]int, cols)
		for c := 0; c < cols; c++ {
			board[r][c] = tiles[r*cols+c]
		}
	}

	return board
}

// CheckMatch returns true if the two positions have the same tile.
func CheckMatch(board [][]int, r1, c1, r2, c2 int) bool {
	return board[r1][c1] == board[r2][c2]
}

// BuildVisibleBoard returns a board where matched tiles show their ID and hidden tiles are -1.
func BuildVisibleBoard(board [][]int, matched [][]bool) [][]int {
	rows := len(board)
	cols := len(board[0])
	visible := make([][]int, rows)
	for r := 0; r < rows; r++ {
		visible[r] = make([]int, cols)
		for c := 0; c < cols; c++ {
			if matched[r][c] {
				visible[r][c] = board[r][c]
			} else {
				visible[r][c] = -1
			}
		}
	}
	return visible
}

// CountMatched counts the number of matched positions (each pair = 2 matched positions).
func CountMatched(matched [][]bool) int {
	count := 0
	for _, row := range matched {
		for _, m := range row {
			if m {
				count++
			}
		}
	}
	return count / 2 // pairs
}

// InitMatched creates an all-false matched grid.
func InitMatched(rows, cols int) [][]bool {
	matched := make([][]bool, rows)
	for r := 0; r < rows; r++ {
		matched[r] = make([]bool, cols)
	}
	return matched
}

// JSON helpers

func BoardToJSON(board [][]int) (string, error) {
	data, err := json.Marshal(board)
	return string(data), err
}

func BoardFromJSON(data string) ([][]int, error) {
	var board [][]int
	err := json.Unmarshal([]byte(data), &board)
	return board, err
}

func MatchedToJSON(matched [][]bool) (string, error) {
	data, err := json.Marshal(matched)
	return string(data), err
}

func MatchedFromJSON(data string) ([][]bool, error) {
	var matched [][]bool
	if data == "" || data == "[]" {
		return matched, nil
	}
	err := json.Unmarshal([]byte(data), &matched)
	return matched, err
}
