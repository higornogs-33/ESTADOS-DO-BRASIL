

<?php
header('Content-Type: application/json; charset=utf-8');

function responderErro(string $mensagem, int $status = 400): void {
    http_response_code($status);
    echo json_encode(
        ['erro' => $mensagem],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

function consultarApi(string $url, string $servico): void {
    $contexto = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'header' =>
                "User-Agent: EstadosBrasilXAMPP/1.0 (projeto local)\r\n" .
                "Accept: application/json\r\n"
        ]
    ]);

    $resposta = @file_get_contents($url, false, $contexto);

    if ($resposta === false) {
        responderErro(
            "Não foi possível consultar $servico. Verifique a internet.",
            502
        );
    }

    $dados = json_decode($resposta, true);

    if (!is_array($dados)) {
        responderErro("Resposta inválida de $servico.", 502);
    }

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
    exit;
}

$baseIbge = 'https://servicodados.ibge.gov.br/api/v1/localidades';

$estado = trim($_GET['estado'] ?? '');
$cidade = trim($_GET['cidade'] ?? '');
$uf = strtoupper(trim($_GET['uf'] ?? ''));
$rua = trim($_GET['rua'] ?? '');

// 1. Pesquisa de ruas
if ($rua !== '') {
    if ($cidade === '' || $uf === '') {
        responderErro('Informe o município e a UF.');
    }

    if (
        mb_strlen($cidade) > 100 ||
        mb_strlen($rua) > 150 ||
        !preg_match('/^[A-Z]{2}$/', $uf)
    ) {
        responderErro('Município, rua ou UF inválidos.');
    }

    $parametros = http_build_query([
        'street' => $rua,
        'city' => $cidade,
        'state' => $uf,
        'country' => 'Brasil',
        'format' => 'jsonv2',
        'addressdetails' => 1,
        'limit' => 10
    ]);

    $url = 'https://nominatim.openstreetmap.org/search?' .
        $parametros;

    consultarApi($url, 'OpenStreetMap');
}

// 2. Municípios de um estado
if ($estado !== '') {
    if (!preg_match('/^\d{2}$/', $estado)) {
        responderErro('Código de estado inválido.');
    }

    consultarApi(
        "$baseIbge/estados/$estado/municipios?orderBy=nome",
        'IBGE'
    );
}

// 3. Lista de estados
consultarApi("$baseIbge/estados?orderBy=nome", 'IBGE');
