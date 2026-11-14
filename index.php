
<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conexion = new mysqli("localhost", "root", "", "skynet");
$conexion->set_charset("utf8mb4");

function limpiar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$error = "";

try {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $accion = $_POST["accion"] ?? "";

        if ($accion === "guardar_cliente") {
            $id = (int)($_POST["id"] ?? 0);
            $nombre = trim($_POST["nombre"] ?? "");
            $correo = trim($_POST["correo"] ?? "");

            if ($nombre === "" || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Ingresa un nombre y correo válido.");
            }

            if ($id > 0) {
                $stmt = $conexion->prepare(
                    "UPDATE clientes SET nombre=?, correo=? WHERE id=?"
                );
                $stmt->bind_param("ssi", $nombre, $correo, $id);
            } else {
                $stmt = $conexion->prepare(
                    "INSERT INTO clientes (nombre, correo) VALUES (?, ?)"
                );
                $stmt->bind_param("ss", $nombre, $correo);
            }
            $stmt->execute();
        }

        if ($accion === "eliminar_cliente") {
            $id = (int)($_POST["id"] ?? 0);
            $stmt = $conexion->prepare("DELETE FROM clientes WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }

        if ($accion === "guardar_cuenta") {
            $id = (int)($_POST["id"] ?? 0);
            $cliente = (int)($_POST["cliente_id"] ?? 0);
            $numero = trim($_POST["numero_cuenta"] ?? "");
            $tipo = $_POST["tipo_cuenta"] ?? "";
            $saldo = $_POST["saldo"] ?? "";

            if ($cliente <= 0 || $numero === "" ||
                !in_array($tipo, ["Ahorro", "Monetaria"], true) ||
                !is_numeric($saldo) || (float)$saldo < 0) {
                throw new Exception("Verifica los datos de la cuenta.");
            }

            $saldo = (float)$saldo;

            if ($id > 0) {
                $stmt = $conexion->prepare(
                    "UPDATE cuentas SET cliente_id=?, numero_cuenta=?,
                     tipo_cuenta=?, saldo=? WHERE id=?"
                );
                $stmt->bind_param(
                    "issdi", $cliente, $numero, $tipo, $saldo, $id
                );
            } else {
                $stmt = $conexion->prepare(
                    "INSERT INTO cuentas
                     (cliente_id, numero_cuenta, tipo_cuenta, saldo)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "issd", $cliente, $numero, $tipo, $saldo
                );
            }
            $stmt->execute();
        }

        if ($accion === "eliminar_cuenta") {
            $id = (int)($_POST["id"] ?? 0);
            $stmt = $conexion->prepare("DELETE FROM cuentas WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }

        header("Location: index.php?exito=1");
        exit;
    }
} catch (mysqli_sql_exception $e) {
    $error = $e->getCode() == 1062
        ? "El correo o número de cuenta ya está registrado."
        : "No se pudo guardar la información.";
} catch (Exception $e) {
    $error = $e->getMessage();
}

$clientes = $conexion->query(
    "SELECT * FROM clientes ORDER BY id DESC"
)->fetch_all(MYSQLI_ASSOC);

$cuentas = $conexion->query(
    "SELECT cuentas.*, clientes.nombre AS cliente
     FROM cuentas
     INNER JOIN clientes ON cuentas.cliente_id = clientes.id
     ORDER BY cuentas.id DESC"
)->fetch_all(MYSQLI_ASSOC);

$editarCliente = null;
$editarCuenta = null;

if (isset($_GET["editar_cliente"])) {
    $id = (int)$_GET["editar_cliente"];
    $stmt = $conexion->prepare("SELECT * FROM clientes WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editarCliente = $stmt->get_result()->fetch_assoc();
}

if (isset($_GET["editar_cuenta"])) {
    $id = (int)$_GET["editar_cuenta"];
    $stmt = $conexion->prepare("SELECT * FROM cuentas WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editarCuenta = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SkyNet | Gestión de Clientes y Cuentas</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}
header {
    background: #1d4ed8;
    color: white;
    padding: 25px 7%;
}
header h1 { margin: 0; }
header p { margin-bottom: 0; }
main {
    max-width: 1150px;
    margin: 30px auto;
    padding: 0 15px;
}
.panel {
    background: white;
    padding: 25px;
    margin-bottom: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 12px #0000000a;
}
h2 { color: #1d4ed8; }
.formulario {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
input, select {
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    flex: 1;
    min-width: 170px;
}
button, .boton {
    background: #2563eb;
    color: white;
    padding: 11px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
}
.editar { background: #f59e0b; }
.eliminar { background: #dc2626; }
.cancelar { background: #64748b; }
.tabla { overflow-x: auto; }
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
th, td {
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}
th { background: #dbeafe; }
.acciones {
    display: flex;
    gap: 7px;
    align-items: center;
}
.acciones form { margin: 0; }
.mensaje {
    padding: 14px;
    border-radius: 6px;
    margin-bottom: 15px;
}
.exito { background: #dcfce7; color: #166534; }
.error { background: #fee2e2; color: #991b1b; }
</style>
</head>
<body>

<header>
    <h1>SkyNet</h1>
    <p>Sistema de Gestión de Clientes y Cuentas</p>
</header>

<main>

<?php if (isset($_GET["exito"])): ?>
<div class="mensaje exito">Operación realizada correctamente.</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mensaje error"><?= limpiar($error) ?></div>
<?php endif; ?>

<section class="panel">
<h2><?= $editarCliente ? "Editar cliente" : "Registrar cliente" ?></h2>

<form method="POST" class="formulario">
    <input type="hidden" name="accion" value="guardar_cliente">
    <input type="hidden" name="id"
           value="<?= limpiar($editarCliente["id"] ?? 0) ?>">

    <input name="nombre" placeholder="Nombre completo"
           value="<?= limpiar($editarCliente["nombre"] ?? "") ?>" required>

    <input type="email" name="correo" placeholder="Correo electrónico"
           value="<?= limpiar($editarCliente["correo"] ?? "") ?>" required>

    <button type="submit">
        <?= $editarCliente ? "Actualizar cliente" : "Registrar cliente" ?>
    </button>

    <?php if ($editarCliente): ?>
    <a href="index.php" class="boton cancelar">Cancelar</a>
    <?php endif; ?>
</form>
</section>

<section class="panel">
<h2>Clientes registrados</h2>
<div class="tabla">
<table>
<thead>
<tr>
    <th>ID</th>
    <th>Nombre</th>
    <th>Correo</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach ($clientes as $cliente): ?>
<tr>
    <td><?= limpiar($cliente["id"]) ?></td>
    <td><?= limpiar($cliente["nombre"]) ?></td>
    <td><?= limpiar($cliente["correo"]) ?></td>
    <td>
        <div class="acciones">
            <a class="boton editar"
               href="?editar_cliente=<?= (int)$cliente["id"] ?>">
               Editar
            </a>
            <form method="POST"
                  onsubmit="return confirm('¿Eliminar cliente y todas sus cuentas?')">
                <input type="hidden" name="accion" value="eliminar_cliente">
                <input type="hidden" name="id"
                       value="<?= (int)$cliente["id"] ?>">
                <button class="eliminar">Eliminar</button>
            </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>

<section class="panel">
<h2><?= $editarCuenta ? "Editar cuenta" : "Registrar cuenta bancaria" ?></h2>

<form method="POST" class="formulario">
    <input type="hidden" name="accion" value="guardar_cuenta">
    <input type="hidden" name="id"
           value="<?= limpiar($editarCuenta["id"] ?? 0) ?>">

    <select name="cliente_id" required>
        <option value="">Seleccionar cliente</option>
        <?php foreach ($clientes as $cliente): ?>
        <option value="<?= (int)$cliente["id"] ?>"
            <?= ($editarCuenta["cliente_id"] ?? "") == $cliente["id"]
                ? "selected" : "" ?>>
            <?= limpiar($cliente["nombre"]) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <input name="numero_cuenta" placeholder="Número de cuenta"
           value="<?= limpiar($editarCuenta["numero_cuenta"] ?? "") ?>"
           required>

    <select name="tipo_cuenta" required>
        <option value="">Tipo de cuenta</option>
        <?php foreach (["Ahorro", "Monetaria"] as $tipo): ?>
        <option value="<?= $tipo ?>"
            <?= ($editarCuenta["tipo_cuenta"] ?? "") === $tipo
                ? "selected" : "" ?>>
            <?= $tipo ?>
        </option>
        <?php endforeach; ?>
    </select>

    <input type="number" name="saldo" step="0.01" min="0"
           placeholder="Saldo inicial"
           value="<?= limpiar($editarCuenta["saldo"] ?? "0.00") ?>"
           required>

    <button type="submit">
        <?= $editarCuenta ? "Actualizar cuenta" : "Registrar cuenta" ?>
    </button>

    <?php if ($editarCuenta): ?>
    <a href="index.php" class="boton cancelar">Cancelar</a>
    <?php endif; ?>
</form>
</section>

<section class="panel">
<h2>Cuentas bancarias registradas</h2>
<div class="tabla">
<table>
<thead>
<tr>
    <th>ID</th>
    <th>Cliente</th>
    <th>Número de cuenta</th>
    <th>Tipo</th>
    <th>Saldo</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach ($cuentas as $cuenta): ?>
<tr>
    <td><?= limpiar($cuenta["id"]) ?></td>
    <td><?= limpiar($cuenta["cliente"]) ?></td>
    <td><?= limpiar($cuenta["numero_cuenta"]) ?></td>
    <td><?= limpiar($cuenta["tipo_cuenta"]) ?></td>
    <td>Q <?= number_format((float)$cuenta["saldo"], 2) ?></td>
    <td>
        <div class="acciones">
            <a class="boton editar"
               href="?editar_cuenta=<?= (int)$cuenta["id"] ?>">
               Editar
            </a>
            <form method="POST"
                  onsubmit="return confirm('¿Eliminar esta cuenta?')">
                <input type="hidden" name="accion" value="eliminar_cuenta">
                <input type="hidden" name="id"
                       value="<?= (int)$cuenta["id"] ?>">
                <button class="eliminar">Eliminar</button>
            </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>

</main>
</body>
</html>
