package mastermind

import (
	"encoding/json"
	"errors"
)

const (
	CodeLength = 4
	MaxGuesses = 10
)

var Colors = []string{"red", "orange", "yellow", "green", "blue", "purple", "pink", "brown"}

// ValidateCode checks if the code is valid
func ValidateCode(code []int, numColors int, allowRepeats bool) error {
	if len(code) != CodeLength {
		return errors.New("code must have exactly 4 colors")
	}

	seen := make(map[int]bool)
	for i, c := range code {
		if c < 0 || c >= numColors {
			return errors.New("invalid color index at position " + string(rune('1'+i)))
		}
		if !allowRepeats {
			if seen[c] {
				return errors.New("duplicate colors not allowed")
			}
			seen[c] = true
		}
	}

	return nil
}

// ValidateNumColors ensures num_colors is valid (4, 6, or 8)
func ValidateNumColors(n int) int {
	if n == 4 || n == 6 || n == 8 {
		return n
	}
	return 6 // default
}

// EvaluateGuess compares guess against secret and returns feedback
// correct = number of right color in right position (black pegs)
// misplaced = number of right color in wrong position (white pegs)
func EvaluateGuess(secret, guess []int) (correct, misplaced int) {
	// Count matches in correct position
	secretRemaining := make([]int, CodeLength)
	guessRemaining := make([]int, CodeLength)

	for i := range CodeLength {
		if secret[i] == guess[i] {
			correct++
		} else {
			secretRemaining[i] = secret[i] + 1 // +1 to differentiate from 0
			guessRemaining[i] = guess[i] + 1
		}
	}

	// Count misplaced (right color, wrong position)
	secretColorCount := make(map[int]int)
	for _, c := range secretRemaining {
		if c > 0 {
			secretColorCount[c-1]++
		}
	}

	for _, c := range guessRemaining {
		if c > 0 && secretColorCount[c-1] > 0 {
			misplaced++
			secretColorCount[c-1]--
		}
	}

	return correct, misplaced
}

// CheckWinCondition determines if the game is over and who won
// Returns: gameOver, winnerID
// winnerID is nil for draw, or the ID of the winner
func CheckWinCondition(
	player1ID, player2ID int64,
	p1Guesses, p2Guesses []GuessResult,
	p1Secret, p2Secret []int,
	maxGuesses int,
) (gameOver bool, winnerID *int64) {
	// Find if each player has cracked the code
	var p1Cracked, p2Cracked bool
	var p1CrackRound, p2CrackRound int

	for _, g := range p1Guesses {
		if g.Correct == CodeLength {
			p1Cracked = true
			p1CrackRound = g.GuessNumber
			break
		}
	}

	for _, g := range p2Guesses {
		if g.Correct == CodeLength {
			p2Cracked = true
			p2CrackRound = g.GuessNumber
			break
		}
	}

	// Both cracked on same round
	if p1Cracked && p2Cracked && p1CrackRound == p2CrackRound {
		// Count total guesses
		p1Total := len(p1Guesses)
		p2Total := len(p2Guesses)

		if p1Total < p2Total {
			return true, &player1ID
		} else if p2Total < p1Total {
			return true, &player2ID
		}
		// True tie - draw
		return true, nil
	}

	// One player cracked and other had their turn in same round
	if p1Cracked && len(p2Guesses) >= p1CrackRound {
		// P1 cracked, P2 had their chance
		if p2Cracked {
			// Both cracked but P1 did it in fewer guesses
			if p1CrackRound < p2CrackRound {
				return true, &player1ID
			}
		}
		// P1 cracked, P2 didn't (or cracked later)
		return true, &player1ID
	}

	if p2Cracked && len(p1Guesses) >= p2CrackRound {
		// P2 cracked, P1 had their chance
		if p1Cracked {
			// Both cracked but P2 did it in fewer guesses
			if p2CrackRound < p1CrackRound {
				return true, &player2ID
			}
		}
		// P2 cracked, P1 didn't (or cracked later)
		return true, &player2ID
	}

	// Check if max guesses reached
	if len(p1Guesses) >= maxGuesses && len(p2Guesses) >= maxGuesses {
		// Neither cracked - find who had most "correct" in best guess
		p1Best := 0
		p2Best := 0

		for _, g := range p1Guesses {
			if g.Correct > p1Best {
				p1Best = g.Correct
			}
		}

		for _, g := range p2Guesses {
			if g.Correct > p2Best {
				p2Best = g.Correct
			}
		}

		if p1Best > p2Best {
			return true, &player1ID
		} else if p2Best > p1Best {
			return true, &player2ID
		}
		// Draw
		return true, nil
	}

	// Game not over yet
	return false, nil
}

// GuessResult for internal use
type GuessResult struct {
	Guess       []int
	Correct     int
	Misplaced   int
	GuessNumber int
}

// JSON helpers
func CodeToJSON(code []int) (string, error) {
	data, err := json.Marshal(code)
	return string(data), err
}

func CodeFromJSON(data string) ([]int, error) {
	var code []int
	if data == "" || data == "[]" {
		return code, nil
	}
	err := json.Unmarshal([]byte(data), &code)
	return code, err
}
