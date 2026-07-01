<?php
session_start();
if (!isset($_SESSION['highscore'])) {
    $_SESSION['highscore'] = 0;
}

// Procesar actualización de highscore mediante AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['score'])) {
    $score = intval($_POST['score']);
    if ($score > $_SESSION['highscore']) {
        $_SESSION['highscore'] = $score;
    }
    echo json_encode(['highscore' => $_SESSION['highscore']]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tetris Clásico PHP</title>
    <style>
        body {
            background: #1a1a2e;
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            overflow: hidden;
        }

        h1 {
            margin: 10px 0;
            color: #e94560;
            text-shadow: 0 0 10px rgba(233, 69, 96, 0.5);
            font-size: 2.5rem;
        }

        .game-container {
            display: flex;
            gap: 20px;
            background: #16213e;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            border: 2px solid #0f3460;
        }

        canvas {
            border: 4px solid #0f3460;
            background-color: #0f172a;
            border-radius: 5px;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 150px;
        }

        .info-box {
            background: #0f3460;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
        }

        .info-box h2 {
            margin: 0 0 5px 0;
            font-size: 0.9rem;
            text-transform: uppercase;
            color: #4e9f3d;
            letter-spacing: 1px;
        }

        .info-box div {
            font-size: 1.5rem;
            font-weight: bold;
            color: #fff;
        }

        .btn {
            background: #e94560;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
            width: 100%;
            text-transform: uppercase;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }

        .btn:hover {
            background: #ff5e7e;
        }

        .controls-hint {
            margin-top: 15px;
            font-size: 0.8rem;
            color: #8a8a8a;
            text-align: center;
            line-height: 1.4;
        }
    </style>
</head>
<body>

    <h1>TETRIS</h1>

    <div class="game-container">
        <canvas id="tetris" width="240" height="400"></canvas>
        
        <div class="sidebar">
            <div>
                <div class="info-box">
                    <h2>Puntuación</h2>
                    <div id="score">0</div>
                </div>
                
                <div class="info-box">
                    <h2>Líneas</h2>
                    <div id="lines">0</div>
                </div>

                <div class="info-box">
                    <h2>Máximo</h2>
                    <div id="highscore"><?php echo $_SESSION['highscore']; ?></div>
                </div>
            </div>

            <div>
                <button class="btn" id="start-btn">Jugar</button>
                <div class="controls-hint">
                    <strong>Controles:</strong><br>
                    ← → : Mover<br>
                    ↑ : Rotar<br>
                    ↓ : Caída Rápida<br>
                    Espacio: Pausa
                </div>
            </div>
        </div>
    </div>

    <script>
        const canvas = document.getElementById('tetris');
        const context = canvas.getContext('2d');

        context.scale(20, 20);

        const colors = [
            null,
            '#ff0055', // Z
            '#00ffcc', // S
            '#ffcc00', // T
            '#ff6600', // O
            '#00ccff', // L
            '#9900ff', // J
            '#33ff33'  // I
        ];

        function createMatrix(w, h) {
            const matrix = [];
            while (h--) {
                matrix.push(new Array(w).fill(0));
            }
            return matrix;
        }

        function createPiece(type) {
            if (type === 'T') return [[0, 0, 0], [1, 1, 1], [0, 1, 0]];
            if (type === 'O') return [[2, 2], [2, 2]];
            if (type === 'L') return [[0, 3, 0], [0, 3, 0], [0, 3, 3]];
            if (type === 'J') return [[0, 4, 0], [0, 4, 0], [4, 4, 0]];
            if (type === 'I') return [[0, 5, 0, 0], [0, 5, 0, 0], [0, 5, 0, 0], [0, 5, 0, 0]];
            if (type === 'S') return [[0, 6, 6], [6, 6, 0], [0, 0, 0]];
            if (type === 'Z') return [[7, 7, 0], [0, 7, 7], [0, 0, 0]];
        }

        let arena = createMatrix(12, 20);
        const player = { pos: {x: 0, y: 0}, matrix: null, score: 0, lines: 0 };

        function collide(arena, player) {
            const [m, o] = [player.matrix, player.pos];
            for (let y = 0; y < m.length; ++y) {
                for (let x = 0; x < m[y].length; ++x) {
                    if (m[y][x] !== 0 && (arena[y + o.y] && arena[y + o.y][x + o.x]) !== 0) {
                        return true;
                    }
                }
            }
            return false;
        }

        function merge(arena, player) {
            player.matrix.forEach((row, y) => {
                row.forEach((value, x) => {
                    if (value !== 0) {
                        arena[y + player.pos.y][x + player.pos.x] = value;
                    }
                });
            });
        }

        function draw() {
            context.fillStyle = '#0f172a';
            context.fillRect(0, 0, canvas.width, canvas.height);
            drawMatrix(arena, {x: 0, y: 0});
            if (player.matrix) {
                drawMatrix(player.matrix, player.pos);
            }
        }

        function drawMatrix(matrix, offset) {
            matrix.forEach((row, y) => {
                row.forEach((value, x) => {
                    if (value !== 0) {
                        context.fillStyle = colors[value];
                        context.fillRect(x + offset.x, y + offset.y, 1, 1);
                        context.strokeStyle = '#16213e';
                        context.lineWidth = 0.05;
                        context.strokeRect(x + offset.x, y + offset.y, 1, 1);
                    }
                });
            });
        }

        function arenaSweep() {
            let rowCount = 1;
            outer: for (let y = arena.length - 1; y > 0; --y) {
                for (let x = 0; x < arena[y].length; ++x) {
                    if (arena[y][x] === 0) continue outer;
                }
                const row = arena.splice(y, 1)[0].fill(0);
                arena.unshift(row);
                ++y;
                player.score += rowCount * 10;
                player.lines++;
                rowCount *= 2;
            }
        }

        let dropCounter = 0;
        let dropInterval = 1000;
        let lastTime = 0;
        let isPaused = false;
        let gameOver = true; // Empieza en true para que no corra antes de darle al botón
        let gameStarted = false;

        function playerDrop() {
            player.pos.y++;
            if (collide(arena, player)) {
                player.pos.y--;
                merge(arena, player);
                playerReset();
                arenaSweep();
                updateScore();
            }
            dropCounter = 0;
        }

        function playerMove(dir) {
            player.pos.x += dir;
            if (collide(arena, player)) {
                player.pos.x -= dir;
            }
        }

        function playerReset() {
            const pieces = 'ILJOTSZ';
            player.matrix = createPiece(pieces[pieces.length * Math.random() | 0]);
            player.pos.y = 0;
            player.pos.x = (arena[0].length / 2 | 0) - (player.matrix[0].length / 2 | 0);

            if (collide(arena, player)) {
                gameOver = true;
                alert('¡Juego Terminado! Tu puntuación final es: ' + player.score);
                sendHighScore(player.score);
            }
        }

        function playerRotate(dir) {
            const pos = player.pos.x;
            let offset = 1;
            rotate(player.matrix, dir);
            while (collide(arena, player)) {
                player.pos.x += offset;
                offset = -(offset + (offset > 0 ? 1 : -1));
                if (offset > player.matrix[0].length) {
                    rotate(player.matrix, -dir);
                    player.pos.x = pos;
                    return;
                }
            }
        }

        function rotate(matrix, dir) {
            for (let y = 0; y < matrix.length; ++y) {
                for (let x = 0; x < y; ++x) {
                    [matrix[x][y], matrix[y][x]] = [matrix[y][x], matrix[x][y]];
                }
            }
            if (dir > 0) matrix.forEach(row => row.reverse());
            else matrix.reverse();
        }

        function sendHighScore(score) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'score=' + score
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('highscore').innerText = data.highscore;
            }).catch(err => console.log(err));
        }

        function updateScore() {
            document.getElementById('score').innerText = player.score;
            document.getElementById('lines').innerText = player.lines;
        }

        function update(time = 0) {
            if (gameOver || isPaused) {
                lastTime = time; // Evita saltos de tiempo masivos tras pausar
                requestAnimationFrame(update);
                return;
            }

            const deltaTime = time - lastTime;
            lastTime = time;

            dropCounter += deltaTime;
            if (dropCounter > dropInterval) {
                playerDrop();
            }

            draw();
            requestAnimationFrame(update);
        }

        document.addEventListener('keydown', event => {
            if (gameOver || isPaused) {
                if (event.keyCode === 32 && gameStarted && !gameOver) { // Alternar pausa con Espacio
                    isPaused = !isPaused;
                    if (isPaused) {
                        context.fillStyle = 'rgba(0,0,0,0.5)';
                        context.fillRect(0, 0, canvas.width, canvas.height);
                        context.fillStyle = '#fff';
                        context.font = '1.5px sans-serif';
                        context.fillText('PAUSA', 3.5, 10);
                    }
                }
                return;
            }

            if (event.keyCode === 37) playerMove(-1);
            if (event.keyCode === 39) playerMove(1);
            if (event.keyCode === 40) playerDrop();
            if (event.keyCode === 38) playerRotate(1);
            if (event.keyCode === 32) { 
                isPaused = true;
            }
        });

        document.getElementById('start-btn').addEventListener('click', () => {
            arena = createMatrix(12, 20);
            player.score = 0;
            player.lines = 0;
            gameOver = false;
            isPaused = false;
            gameStarted = true;
            updateScore();
            playerReset();
            document.getElementById('start-btn').blur(); 
        });

        // Dibujar el tablero vacío inicial
        draw();
        // Iniciar el bucle de renderizado de forma segura
        requestAnimationFrame(update);
    </script>
</body>
</html>