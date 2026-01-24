package scrabble

import (
	"bufio"
	_ "embed"
	"strings"
	"sync"
)

//go:embed words.txt
var wordsFile string

var (
	dictionary map[string]bool
	dictOnce   sync.Once
)

// loadDictionary initializes the dictionary from the embedded word list
func loadDictionary() {
	dictionary = make(map[string]bool)
	scanner := bufio.NewScanner(strings.NewReader(wordsFile))
	for scanner.Scan() {
		word := strings.ToUpper(strings.TrimSpace(scanner.Text()))
		if len(word) >= 2 {
			dictionary[word] = true
		}
	}
}

// IsValidWord checks if a word exists in the dictionary
func IsValidWord(word string) bool {
	dictOnce.Do(loadDictionary)
	return dictionary[strings.ToUpper(word)]
}

// GetDictionarySize returns the number of words in the dictionary
func GetDictionarySize() int {
	dictOnce.Do(loadDictionary)
	return len(dictionary)
}
