const express = require("express");
const http = require("http");
const socketIo = require("socket.io");
const mysql = require("mysql2");

const app = express();
const server = http.createServer(app);
const io = socketIo(server);

const db = mysql.createConnection({
    host: "localhost",
    user: "samann1_location_db",
    password: "location@2025",
    database: "samann1_location_db",
});

io.on("connection", (socket) => {
    console.log("User connected:", socket.id);

    socket.on("move", (data) => {
        const { index, player } = data;
        db.query("SELECT * FROM game WHERE id = 1", (err, result) => {
            if (err) return;
            let game = result[0];
            let board = game.board.split("");

            if (board[index] === " " && ((game.turn === "X" && player === game.x_player) || (game.turn === "O" && player === game.o_player))) {
                board[index] = game.turn;
                let newBoard = board.join("");
                let newTurn = game.turn === "X" ? "O" : "X";

                db.query("UPDATE game SET board=?, turn=? WHERE id=1", [newBoard, newTurn], () => {
                    io.emit("update", { board: newBoard, turn: newTurn });
                });
            }
        });
    });

    socket.on("disconnect", () => {
        console.log("User disconnected:", socket.id);
    });
});

server.listen(3000, () => console.log("Server running on port 3000"));
