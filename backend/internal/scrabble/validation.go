package scrabble

import (
	"errors"
	"math/rand"
	"sort"

	"altech/internal/models"
)

var (
	ErrNotYourTurn       = errors.New("not your turn")
	ErrGameNotActive     = errors.New("game is not active")
	ErrInvalidTiles      = errors.New("tiles not in your rack")
	ErrEmptyMove         = errors.New("no tiles placed")
	ErrTilesNotInLine    = errors.New("tiles must be in a single row or column")
	ErrGapInTiles        = errors.New("tiles must be contiguous")
	ErrFirstMoveNoCenter = errors.New("first move must cover center square")
	ErrNotConnected      = errors.New("tiles must connect to existing tiles")
	ErrInvalidWord       = errors.New("invalid word formed")
	ErrNotEnoughTiles    = errors.New("not enough tiles in bag to exchange")
)

// ValidateAndScoreMove validates a move and returns the score and words formed
func ValidateAndScoreMove(board [][]models.Tile, rack []models.Tile, tiles []models.PlacedTile) (int, []string, error) {
	if len(tiles) == 0 {
		return 0, nil, ErrEmptyMove
	}

	// Check all tiles are in rack and track which are blanks
	rackCopy := make([]models.Tile, len(rack))
	copy(rackCopy, rack)

	blankPositions := make(map[int]bool) // index in tiles that came from blanks

	for idx, t := range tiles {
		found := false
		for i, r := range rackCopy {
			// First try exact match
			if r.Letter == t.Letter {
				rackCopy = append(rackCopy[:i], rackCopy[i+1:]...)
				found = true
				break
			}
		}
		if !found {
			// Try blank tile match
			for i, r := range rackCopy {
				if r.Letter == " " && len(t.Letter) == 1 {
					rackCopy = append(rackCopy[:i], rackCopy[i+1:]...)
					blankPositions[idx] = true
					found = true
					break
				}
			}
		}
		if !found {
			return 0, nil, ErrInvalidTiles
		}
	}

	// Validate tile positions
	if err := validateTilePositions(board, tiles); err != nil {
		return 0, nil, err
	}

	// Apply tiles to a temporary board
	tempBoard := copyBoard(board)
	for idx, t := range tiles {
		value := LetterValues[t.Letter]
		if blankPositions[idx] {
			value = 0 // Blank tiles are worth 0 points
		}
		tempBoard[t.Row][t.Col] = models.Tile{
			Letter: t.Letter,
			Value:  value,
			IsNew:  true,
		}
	}

	// Find all words formed
	words, wordPositions := findAllWords(tempBoard, tiles)
	if len(words) == 0 {
		return 0, nil, ErrInvalidWord
	}

	// Validate all words
	for _, word := range words {
		if !IsValidWord(word) {
			return 0, nil, ErrInvalidWord
		}
	}

	// Calculate score
	score := calculateScore(tempBoard, wordPositions)

	// Bingo bonus (using all 7 tiles)
	if len(tiles) == 7 {
		score += 50
	}

	return score, words, nil
}

func validateTilePositions(board [][]models.Tile, tiles []models.PlacedTile) error {
	// Check bounds and that squares are empty
	for _, t := range tiles {
		if t.Row < 0 || t.Row >= BoardSize || t.Col < 0 || t.Col >= BoardSize {
			return ErrTilesNotInLine
		}
		if board[t.Row][t.Col].Letter != "" {
			return ErrInvalidTiles
		}
	}

	// Check all tiles are in same row or column
	sameRow := true
	sameCol := true
	for i := 1; i < len(tiles); i++ {
		if tiles[i].Row != tiles[0].Row {
			sameRow = false
		}
		if tiles[i].Col != tiles[0].Col {
			sameCol = false
		}
	}
	if !sameRow && !sameCol {
		return ErrTilesNotInLine
	}

	// Sort tiles by position
	sortedTiles := make([]models.PlacedTile, len(tiles))
	copy(sortedTiles, tiles)
	if sameRow {
		sort.Slice(sortedTiles, func(i, j int) bool {
			return sortedTiles[i].Col < sortedTiles[j].Col
		})
	} else {
		sort.Slice(sortedTiles, func(i, j int) bool {
			return sortedTiles[i].Row < sortedTiles[j].Row
		})
	}

	// Check for contiguity (no gaps unless filled by existing tiles)
	for i := 0; i < len(sortedTiles)-1; i++ {
		var gap int
		if sameRow {
			gap = sortedTiles[i+1].Col - sortedTiles[i].Col
		} else {
			gap = sortedTiles[i+1].Row - sortedTiles[i].Row
		}

		if gap > 1 {
			// Check that the gap is filled by existing tiles
			for j := 1; j < gap; j++ {
				var r, c int
				if sameRow {
					r = sortedTiles[i].Row
					c = sortedTiles[i].Col + j
				} else {
					r = sortedTiles[i].Row + j
					c = sortedTiles[i].Col
				}
				if board[r][c].Letter == "" {
					return ErrGapInTiles
				}
			}
		}
	}

	// Check first move covers center
	boardEmpty := IsBoardEmpty(board)
	if boardEmpty {
		centerCovered := false
		for _, t := range tiles {
			if t.Row == 7 && t.Col == 7 {
				centerCovered = true
				break
			}
		}
		if !centerCovered {
			return ErrFirstMoveNoCenter
		}
		// First move is valid if it covers center
		return nil
	}

	// Check that tiles connect to existing tiles
	connected := false
	for _, t := range tiles {
		// Check adjacent squares
		neighbors := []struct{ r, c int }{
			{t.Row - 1, t.Col},
			{t.Row + 1, t.Col},
			{t.Row, t.Col - 1},
			{t.Row, t.Col + 1},
		}
		for _, n := range neighbors {
			if n.r >= 0 && n.r < BoardSize && n.c >= 0 && n.c < BoardSize {
				if board[n.r][n.c].Letter != "" {
					connected = true
					break
				}
			}
		}
		if connected {
			break
		}
	}
	if !connected {
		return ErrNotConnected
	}

	return nil
}

type wordPosition struct {
	word   string
	tiles  []struct{ row, col int }
}

func findAllWords(board [][]models.Tile, newTiles []models.PlacedTile) ([]string, []wordPosition) {
	words := []string{}
	positions := []wordPosition{}

	// Create set of new tile positions for quick lookup
	newTileSet := make(map[string]bool)
	for _, t := range newTiles {
		newTileSet[posKey(t.Row, t.Col)] = true
	}

	// Determine direction of placement
	horizontal := len(newTiles) == 1 || (len(newTiles) > 1 && newTiles[0].Row == newTiles[1].Row)

	// Find the main word
	var mainStart, mainEnd int
	row, col := newTiles[0].Row, newTiles[0].Col

	if horizontal {
		// Find extent of horizontal word
		mainStart = col
		for mainStart > 0 && board[row][mainStart-1].Letter != "" {
			mainStart--
		}
		mainEnd = col
		for mainEnd < BoardSize-1 && board[row][mainEnd+1].Letter != "" {
			mainEnd++
		}

		// Also extend through new tiles
		for _, t := range newTiles {
			if t.Col < mainStart {
				mainStart = t.Col
			}
			if t.Col > mainEnd {
				mainEnd = t.Col
			}
		}

		// Build the main word
		if mainEnd > mainStart {
			wp := wordPosition{}
			word := ""
			for c := mainStart; c <= mainEnd; c++ {
				if board[row][c].Letter != "" {
					word += board[row][c].Letter
					wp.tiles = append(wp.tiles, struct{ row, col int }{row, c})
				}
			}
			if len(word) >= 2 {
				words = append(words, word)
				wp.word = word
				positions = append(positions, wp)
			}
		}

		// Find perpendicular words for each new tile
		for _, t := range newTiles {
			rStart := t.Row
			for rStart > 0 && board[rStart-1][t.Col].Letter != "" {
				rStart--
			}
			rEnd := t.Row
			for rEnd < BoardSize-1 && board[rEnd+1][t.Col].Letter != "" {
				rEnd++
			}

			if rEnd > rStart {
				wp := wordPosition{}
				word := ""
				for r := rStart; r <= rEnd; r++ {
					if board[r][t.Col].Letter != "" {
						word += board[r][t.Col].Letter
						wp.tiles = append(wp.tiles, struct{ row, col int }{r, t.Col})
					}
				}
				if len(word) >= 2 {
					words = append(words, word)
					wp.word = word
					positions = append(positions, wp)
				}
			}
		}
	} else {
		// Vertical placement
		// Find extent of vertical word
		mainStart = row
		for mainStart > 0 && board[mainStart-1][col].Letter != "" {
			mainStart--
		}
		mainEnd = row
		for mainEnd < BoardSize-1 && board[mainEnd+1][col].Letter != "" {
			mainEnd++
		}

		// Also extend through new tiles
		for _, t := range newTiles {
			if t.Row < mainStart {
				mainStart = t.Row
			}
			if t.Row > mainEnd {
				mainEnd = t.Row
			}
		}

		// Build the main word
		if mainEnd > mainStart {
			wp := wordPosition{}
			word := ""
			for r := mainStart; r <= mainEnd; r++ {
				if board[r][col].Letter != "" {
					word += board[r][col].Letter
					wp.tiles = append(wp.tiles, struct{ row, col int }{r, col})
				}
			}
			if len(word) >= 2 {
				words = append(words, word)
				wp.word = word
				positions = append(positions, wp)
			}
		}

		// Find perpendicular words for each new tile
		for _, t := range newTiles {
			cStart := t.Col
			for cStart > 0 && board[t.Row][cStart-1].Letter != "" {
				cStart--
			}
			cEnd := t.Col
			for cEnd < BoardSize-1 && board[t.Row][cEnd+1].Letter != "" {
				cEnd++
			}

			if cEnd > cStart {
				wp := wordPosition{}
				word := ""
				for c := cStart; c <= cEnd; c++ {
					if board[t.Row][c].Letter != "" {
						word += board[t.Row][c].Letter
						wp.tiles = append(wp.tiles, struct{ row, col int }{t.Row, c})
					}
				}
				if len(word) >= 2 {
					words = append(words, word)
					wp.word = word
					positions = append(positions, wp)
				}
			}
		}
	}

	return words, positions
}

func calculateScore(board [][]models.Tile, wordPositions []wordPosition) int {
	totalScore := 0

	for _, wp := range wordPositions {
		wordScore := 0
		wordMultiplier := 1

		for _, pos := range wp.tiles {
			tile := board[pos.row][pos.col]
			letterScore := tile.Value

			// Only apply bonuses for newly placed tiles
			if tile.IsNew {
				bonus := GetBonusType(pos.row, pos.col)
				switch bonus {
				case DoubleLetter:
					letterScore *= 2
				case TripleLetter:
					letterScore *= 3
				case DoubleWord, Center:
					wordMultiplier *= 2
				case TripleWord:
					wordMultiplier *= 3
				}
			}

			wordScore += letterScore
		}

		totalScore += wordScore * wordMultiplier
	}

	return totalScore
}

func copyBoard(board [][]models.Tile) [][]models.Tile {
	newBoard := make([][]models.Tile, BoardSize)
	for i := range board {
		newBoard[i] = make([]models.Tile, BoardSize)
		copy(newBoard[i], board[i])
	}
	return newBoard
}

func posKey(row, col int) string {
	return string(rune(row)) + "," + string(rune(col))
}

// ApplyMove applies tiles to the board permanently
// It also determines which tiles were blanks based on the rack
func ApplyMove(board [][]models.Tile, rack []models.Tile, tiles []models.PlacedTile) [][]models.Tile {
	newBoard := copyBoard(board)

	// Determine which tiles are blanks (same logic as validation)
	rackCopy := make([]models.Tile, len(rack))
	copy(rackCopy, rack)
	blankPositions := make(map[int]bool)

	for idx, t := range tiles {
		found := false
		for i, r := range rackCopy {
			if r.Letter == t.Letter {
				rackCopy = append(rackCopy[:i], rackCopy[i+1:]...)
				found = true
				break
			}
		}
		if !found {
			for i, r := range rackCopy {
				if r.Letter == " " && len(t.Letter) == 1 {
					rackCopy = append(rackCopy[:i], rackCopy[i+1:]...)
					blankPositions[idx] = true
					found = true
					break
				}
			}
		}
	}

	for idx, t := range tiles {
		value := LetterValues[t.Letter]
		if blankPositions[idx] {
			value = 0
		}
		newBoard[t.Row][t.Col] = models.Tile{
			Letter: t.Letter,
			Value:  value,
			IsNew:  false,
		}
	}
	return newBoard
}

// RemoveTilesFromRack removes placed tiles from the rack
func RemoveTilesFromRack(rack []models.Tile, tiles []models.PlacedTile) []models.Tile {
	newRack := make([]models.Tile, len(rack))
	copy(newRack, rack)

	for _, t := range tiles {
		for i, r := range newRack {
			if r.Letter == t.Letter || (r.Letter == " " && len(t.Letter) == 1) {
				newRack = append(newRack[:i], newRack[i+1:]...)
				break
			}
		}
	}
	return newRack
}

// RefillRack draws tiles from bag to fill rack to 7 tiles
func RefillRack(rack []models.Tile, bag []models.Tile) (newRack []models.Tile, newBag []models.Tile) {
	needed := 7 - len(rack)
	if needed <= 0 {
		return rack, bag
	}

	drawn, remaining := DrawTiles(bag, needed)
	newRack = append(rack, drawn...)
	return newRack, remaining
}

// ValidateExchange validates that tiles can be exchanged
func ValidateExchange(rack []models.Tile, tilesToExchange []string, bagSize int) error {
	if len(tilesToExchange) == 0 {
		return ErrEmptyMove
	}
	if bagSize < 7 {
		return ErrNotEnoughTiles
	}

	// Check all tiles are in rack
	rackCopy := make([]models.Tile, len(rack))
	copy(rackCopy, rack)

	for _, letter := range tilesToExchange {
		found := false
		for i, r := range rackCopy {
			if r.Letter == letter {
				rackCopy = append(rackCopy[:i], rackCopy[i+1:]...)
				found = true
				break
			}
		}
		if !found {
			return ErrInvalidTiles
		}
	}

	return nil
}

// ExchangeTiles removes tiles from rack, adds them back to bag (shuffled), and draws new tiles
func ExchangeTiles(rack []models.Tile, bag []models.Tile, tilesToExchange []string) (newRack []models.Tile, newBag []models.Tile) {
	// Remove tiles from rack
	newRack = make([]models.Tile, len(rack))
	copy(newRack, rack)

	exchangedTiles := []models.Tile{}
	for _, letter := range tilesToExchange {
		for i, r := range newRack {
			if r.Letter == letter {
				exchangedTiles = append(exchangedTiles, r)
				newRack = append(newRack[:i], newRack[i+1:]...)
				break
			}
		}
	}

	// Draw new tiles first
	drawn, remaining := DrawTiles(bag, len(tilesToExchange))
	newRack = append(newRack, drawn...)

	// Add exchanged tiles back to bag
	newBag = append(remaining, exchangedTiles...)

	// Shuffle bag
	shuffleBag(newBag)

	return newRack, newBag
}

func shuffleBag(bag []models.Tile) {
	rand.Shuffle(len(bag), func(i, j int) {
		bag[i], bag[j] = bag[j], bag[i]
	})
}
