CREATE TABLE user(
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	email TEXT,
	password TEXT,
	salt TEXT,
	iter INTEGER
);