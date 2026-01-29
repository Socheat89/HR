<?php
include 'config.php';

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $index = $_POST['index'];
    $player = $_POST['player'];

    $query = "SELECT * FROM game WHERE id = 1";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();

    $board = str_split($row['board']);
    if ($board[$index] === ' ' && (($row['turn'] === 'X' && $player === $row['x_player']) || ($row['turn'] === 'O' && $player === $row['o_player']))) {
        $board[$index] = $row['turn'];
        $new_board = implode('', $board);
        $new_turn = ($row['turn'] === 'X') ? 'O' : 'X';

        // Check Win
        $winner = checkWinner($board);
        if ($winner) {
            if ($winner === 'X') $row['x_score']++;
            if ($winner === 'O') $row['o_score']++;
            $new_board = '         ';  // Reset board
        }

        // Update Database
        $update = "UPDATE game SET board='$new_board', turn='$new_turn', x_score={$row['x_score']}, o_score={$row['o_score']} WHERE id = 1";
        $conn->query($update);
    }
}

function checkWinner($board) {
    $wins = [[0,1,2], [3,4,5], [6,7,8], [0,3,6], [1,4,7], [2,5,8], [0,4,8], [2,4,6]];
    foreach ($wins as $win) {
        if ($board[$win[0]] !== ' ' && $board[$win[0]] === $board[$win[1]] && $board[$win[1]] === $board[$win[2]]) {
            return $board[$win[0]];
        }
    }
    return null;
}

// Get Updated Data
$query = "SELECT * FROM game WHERE id = 1";
$result = $conn->query($query);
echo json_encode($result->fetch_assoc());
?>
