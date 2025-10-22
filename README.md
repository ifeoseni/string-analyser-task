<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## HNG Backend Task 1

# Backend Wizards — Stage 1 Task: String Analysis REST API

## Overview

This project implements the **Stage 1 Backend Wizards Task**, which involves developing a **RESTful API** that analyzes, stores, filters, and retrieves string data with computed properties. Each submitted string is uniquely identified by its **SHA-256 hash**, and the system provides endpoints for analysis, retrieval, deletion, and natural language filtering.

The API is built in **PHP**, following REST conventions, with proper request validation, error handling, and structured JSON responses.

---

## Features

-   **Analyze Strings:** Compute properties such as length, palindrome status, unique characters, word count, SHA-256 hash, and character frequency.
-   **Retrieve Strings:** Fetch analyzed strings by their original value.
-   **Filter Strings:** Retrieve all strings using query parameters such as length, palindrome status, word count, and character inclusion.
-   **Natural Language Filtering:** Interpret human-like queries such as “all single word palindromic strings” or “strings containing the letter z”.
-   **Delete Strings:** Remove previously stored strings by value.
-   **Validation and Error Handling:** Ensures all inputs meet required formats and constraints.

---

## Computed String Properties

For every analyzed string, the following properties are computed and stored:

| Property                  | Description                                                                               |
| ------------------------- | ----------------------------------------------------------------------------------------- |
| `length`                  | Number of characters in the string                                                        |
| `is_palindrome`           | Boolean indicating if the string reads the same forwards and backwards (case-insensitive) |
| `unique_characters`       | Count of distinct characters in the string                                                |
| `word_count`              | Number of words separated by whitespace                                                   |
| `sha256_hash`             | Unique SHA-256 hash of the string                                                         |
| `character_frequency_map` | Object mapping each character to its number of occurrences                                |

---

## API Endpoints

### 1. Create or Analyze String

**POST** `/strings`  
Analyzes a new string and stores its computed properties.

#### Request

```json
{
    "value": "string to analyze"
}
```

#### Success Response (201 Created)

```json
{
    "id": "sha256_hash_value",
    "value": "string to analyze",
    "properties": {
        "length": 17,
        "is_palindrome": false,
        "unique_characters": 12,
        "word_count": 3,
        "sha256_hash": "abc123...",
        "character_frequency_map": {
            "s": 2,
            "t": 3,
            "r": 2
        }
    },
    "created_at": "2025-08-27T10:00:00Z"
}
```

#### Error Responses

| Status                   | Description                        |
| ------------------------ | ---------------------------------- |
| 409 Conflict             | String already exists              |
| 400 Bad Request          | Missing or invalid `"value"` field |
| 422 Unprocessable Entity | Non-string value provided          |

---

### 2. Get Specific String

**GET** `/strings/{string_value}`  
Retrieves an analyzed string by its original value.

#### Success Response (200 OK)

```json
{
    "id": "sha256_hash_value",
    "value": "requested string",
    "properties": {
        /* same as above */
    },
    "created_at": "2025-08-27T10:00:00Z"
}
```

#### Error Response

| Status        | Description                    |
| ------------- | ------------------------------ |
| 404 Not Found | String not found in the system |

---

### 3. Get All Strings with Filtering

**GET** `/strings`  
Retrieve multiple analyzed strings based on query filters.

#### Example Request

```
/strings?is_palindrome=true&min_length=5&max_length=20&word_count=2&contains_character=a
```

#### Success Response (200 OK)

```json
{
    "data": [
        {
            "id": "hash1",
            "value": "string1",
            "properties": {
                /* ... */
            },
            "created_at": "2025-08-27T10:00:00Z"
        }
    ],
    "count": 15,
    "filters_applied": {
        "is_palindrome": true,
        "min_length": 5,
        "max_length": 20,
        "word_count": 2,
        "contains_character": "a"
    }
}
```

#### Query Parameters

| Parameter            | Type    | Description                                |
| -------------------- | ------- | ------------------------------------------ |
| `is_palindrome`      | boolean | Filter by palindrome status                |
| `min_length`         | integer | Minimum length of the string               |
| `max_length`         | integer | Maximum length of the string               |
| `word_count`         | integer | Exact number of words                      |
| `contains_character` | string  | Filter by presence of a specific character |

#### Error Response

| Status          | Description                           |
| --------------- | ------------------------------------- |
| 400 Bad Request | Invalid or malformed query parameters |

---

### 4. Natural Language Filtering

**GET** `/strings/filter-by-natural-language?query=all%20single%20word%20palindromic%20strings`  
Automatically interprets natural language queries into structured filters.

#### Success Response (200 OK)

```json
{
    "data": [
        /* array of matching strings */
    ],
    "count": 3,
    "interpreted_query": {
        "original": "all single word palindromic strings",
        "parsed_filters": {
            "word_count": 1,
            "is_palindrome": true
        }
    }
}
```

#### Example Supported Queries

| Query                                              | Parsed Meaning                           |
| -------------------------------------------------- | ---------------------------------------- |
| "all single word palindromic strings"              | word_count=1, is_palindrome=true         |
| "strings longer than 10 characters"                | min_length=11                            |
| "palindromic strings that contain the first vowel" | is_palindrome=true, contains_character=a |
| "strings containing the letter z"                  | contains_character=z                     |

#### Error Responses

| Status                   | Description                                |
| ------------------------ | ------------------------------------------ |
| 400 Bad Request          | Query could not be parsed                  |
| 422 Unprocessable Entity | Parsed but resulted in conflicting filters |

---

### 5. Delete String

**DELETE** `/strings/{string_value}`  
Removes an analyzed string from the system.

#### Success Response (204 No Content)

```
(no body)
```

#### Error Response

| Status        | Description                    |
| ------------- | ------------------------------ |
| 404 Not Found | String not found in the system |

---

## Example Project Structure

```
string-analyzer/
├── storage/
│   └── app.log
├── analysed_strings.json
├── composer.json
├── strings.php
└── README.md
```

---

## Requirements

-   PHP 8.0 or higher
-   Composer
-   XAMPP or any PHP-compatible web server

---

## Installation and Setup

### Step 1: Clone the Repository

```bash
git clone https://github.com/ifeoseni/string-analyzer-api.git
cd string-analyzer-api
```

### Step 2: Install Dependencies

```bash
composer install
```

### Step 3: Start Local Server

Move the project to your web root (e.g., `C:\xampp\htdocs\string-analyzer`) and start Apache.

Access the API at:

```
http://localhost/string-analyzer/strings
```

---

## Example Workflow (Postman)

1. **POST /strings** with a JSON body to analyze a string.
2. **GET /strings** to view all analyzed strings or apply filters.
3. **GET /strings/{value}** to view one string’s properties.
4. **GET /strings/filter-by-natural-language?query=palindromic+strings** to test natural language queries.
5. **DELETE /strings/{value}** to remove a string.

---

## Error Handling and Validation

-   Ensures input is a non-empty string.
-   Prevents duplicate entries based on SHA-256 hash.
-   Returns descriptive error messages with proper status codes.
-   Validates all query parameters and request bodies before processing.

---

## License

This project is distributed under the **MIT License**.

---

## Author

**Ifeoluwa Oseni**  
Software Engineer  
Email: [ifeoseni@gmail.com](mailto:ifeoseni@gmail.com)  
GitHub: [@ifeoseni](https://github.com/ifeoseni)
