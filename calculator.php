<?php
session_start();

const MAX_HISTORY = 5;
$operations = ['Sum' => '+', 'Sub' => '-', 'Mul' => '×', 'Div' => '÷'];

// Initialize session variables
$_SESSION['token'] = $_SESSION['token'] ?? bin2hex(random_bytes(32));
$_SESSION['history'] = $_SESSION['history'] ?? [];

// State management
$state = ['num1' => '', 'num2' => '', 'operation' => '', 'result' => '', 'error' => ''];
$formattedResult = ''; // Initialize here

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle clear history
    if (isset($_POST['clear_history'])) {
        if (hash_equals($_SESSION['token'], $_POST['token'])) {
            $_SESSION['history'] = [];
        }
        exit(header("Location: ".$_SERVER['PHP_SELF']));
    }

    $state = array_merge($state, $_POST);
    
    if (hash_equals($_SESSION['token'], $state['token'] ?? '')) {
        $state['error'] = '';
        
        // Validation
        $num1 = trim($state['num1']);
        $num2 = trim($state['num2']);
        $operation = $state['operation'];
        
        $valid = true;
        if (!is_numeric($num1)) {
            $state['error'] = 'First number is invalid';
            $valid = false;
        }
        if (!is_numeric($num2)) {
            $state['error'] = 'Second number is invalid';
            $valid = false;
        }
        if (!array_key_exists($operation, $operations)) {
            $state['error'] = 'Invalid operation selected';
            $valid = false;
        }
        
        if ($valid) {
            $num1 = (float)$num1;
            $num2 = (float)$num2;
            
            try {
                $state['result'] = match($operation) {
                    'Sum' => $num1 + $num2,
                    'Sub' => $num1 - $num2,
                    'Mul' => $num1 * $num2,
                    'Div' => $num2 != 0 ? $num1 / $num2 : throw new Exception('Division by zero'),
                };
                
                // Add to history
                array_unshift($_SESSION['history'], [
                    'equation' => "$num1 {$operations[$operation]} $num2",
                    'result' => $state['result'],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                $_SESSION['history'] = array_slice($_SESSION['history'], 0, MAX_HISTORY);
                
            } catch (Exception $e) {
                $state['error'] = $e->getMessage();
            }
        }
    }
}

// Formatting (moved before HTML output)
$formattedResult = $state['result'] !== '' ? (
    floor($state['result']) == $state['result'] ? 
    number_format($state['result'], 0) : 
    number_format($state['result'], 4)
) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earth Tone Calculator</title>
    <style>
        :root {
            --primary: #CCD5AE;
            --background: #E9EDC9;
            --surface: #FEFAE0;
            --secondary: #FAEDCD;
            --accent: #D4A373;
            --text: #4A4A4A;
            --error: #D4A373;
            --radius: 12px;
            --shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        body {
            background: var(--background);
            color: var(--text);
            font-family: 'Segoe UI', system-ui;
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
        }

        .calculator {
            background: var(--surface);
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            width: min(90vw, 400px);
            transition: transform 0.2s;
        }

        .input-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        input {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--secondary);
            border-radius: calc(var(--radius) - 4px);
            background: var(--surface);
            color: var(--text);
            font-size: 1rem;
            transition: all 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--secondary);
        }

        .operations {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
            margin: 1.5rem 0;
        }

        button {
            padding: 1rem;
            border: none;
            border-radius: calc(var(--radius) - 4px);
            background: var(--primary);
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s;
        }

        button:hover {
            background: var(--accent);
            transform: translateY(-2px);
        }

        .result {
            padding: 1rem;
            border-radius: var(--radius);
            margin: 1rem 0;
            background: var(--secondary);
            text-align: center;
            font-size: 1.1rem;
        }

        .history {
            margin-top: 2rem;
            border-top: 2px solid var(--secondary);
            padding-top: 1rem;
        }

        .history-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--secondary);
        }

        .clear-button {
            background: var(--accent) !important;
            color: white !important;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }

        .error {
            background: var(--accent);
            color: white !important;
        }
    </style>
</head>
<body>
    <div class="calculator">
        <form method="post">
            <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
            
            <div class="input-group">
                <input type="number" name="num1" step="any" required
                       value="<?= htmlspecialchars($state['num1']) ?>"
                       placeholder="First number">
            </div>
            
            <div class="input-group">
                <input type="number" name="num2" step="any" required
                       value="<?= htmlspecialchars($state['num2']) ?>"
                       placeholder="Second number">
            </div>

            <div class="operations">
                <?php foreach ($operations as $op => $symbol): ?>
                    <button type="submit" name="operation" value="<?= $op ?>">
                        <?= $symbol ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </form>

        <?php if ($state['error']): ?>
            <div class="result error">
                <?= htmlspecialchars($state['error']) ?>
            </div>
        <?php elseif ($formattedResult !== ''): ?>
            <div class="result">
                <?= htmlspecialchars("{$state['num1']} {$operations[$state['operation']]} {$state['num2']} = $formattedResult") ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['history'])): ?>
            <div class="history">
                <h3>History</h3>
                <?php foreach ($_SESSION['history'] as $entry): ?>
                    <div class="history-item">
                        <span><?= htmlspecialchars($entry['equation']) ?></span>
                        <span><?= htmlspecialchars($entry['result']) ?></span>
                    </div>
                <?php endforeach; ?>
                <form method="post" style="margin-top: 1rem;">
                    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
                    <button type="submit" name="clear_history" class="clear-button">
                        Clear History
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>