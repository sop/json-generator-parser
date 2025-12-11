# JSON Generator Parser

[![CircleCI](https://dl.circleci.com/status-badge/img/gh/sop/json-generator-parser/tree/master.svg?style=svg)](https://dl.circleci.com/status-badge/redirect/gh/sop/json-generator-parser/tree/master)
[![Coverage Status](https://coveralls.io/repos/github/sop/json-generator-parser/badge.svg?branch=master)](https://coveralls.io/github/sop/json-generator-parser?branch=master)
[![License](https://poser.pugx.org/sop/json-generator-parser/license)](https://github.com/sop/json-generator-parser/blob/master/LICENSE)

A PHP library for event-driven recursion free SAX-style JSON parser fed by generator.

## Rationale

Provide a JSON parser with lenient rules that accepts input from an LLM stream.

There are cases when you need a structured LLM response, but want to display
output as soon as there are token stream available.
This library provides a listener interface, that is called as JSON is parsed.

As LLM's may generate a bit wonky JSON, this library has a non-strict option
that permits various JSON errors, which wouldn't change the semantic meaning
of the response.

## Requirements

-   PHP >=8.2

## License

This project is licensed under the MIT License.
