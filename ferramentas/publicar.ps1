# janela para publicar o site no servidor da UA e sincronizar as contas (precisa da VPN ligada)
# abre-se com o publicar.bat que está na raiz do projeto ou com o atalho "I'm Gamer publicar" do menu Iniciar

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

# funções do windows para usar as fontes do site, pôr a barra de título escura e mexer no scroll do registo
Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
public class Windows {
    [DllImport("gdi32.dll")]
    public static extern IntPtr AddFontMemResourceEx(IntPtr dados, uint tamanho, IntPtr reservado, ref uint quantas);
    [DllImport("dwmapi.dll")]
    public static extern int DwmSetWindowAttribute(IntPtr janela, int atributo, ref int valor, int tamanho);
    [DllImport("user32.dll")]
    public static extern IntPtr SendMessage(IntPtr janela, int mensagem, IntPtr a, IntPtr b);
}
'@

$pastaProjeto = Split-Path $PSScriptRoot -Parent
$servidor = '\\arca.ua.pt\Hosting\esan-tesp-ds-paw.web.ua.pt\tesp-ds-g17'
$site = 'https://esan-tesp-ds-paw.web.ua.pt/tesp-ds-g17/projeto/'
$credenciais = "$pastaProjeto\projeto\config\credentials.php"

# extensões do php que o site usa (bd, pedidos às apis, textos com acentos e ficheiros)
$extensoes = @('curl', 'fileinfo', 'mbstring', 'openssl', 'pdo_mysql')

# cores iguais às do site (common/style.css)
$corFundo = [System.Drawing.Color]::FromArgb(14, 15, 18)
$corCaixa = [System.Drawing.Color]::FromArgb(22, 23, 27)
$corBorda = [System.Drawing.Color]::FromArgb(36, 38, 43)
$corTexto = [System.Drawing.Color]::FromArgb(242, 242, 240)
$corDim = [System.Drawing.Color]::FromArgb(143, 145, 152)
# cor do puxador da barra de scroll, um pouco mais clara que a borda para se ver
$corPuxador = [System.Drawing.Color]::FromArgb(62, 65, 72)

# ---------- fontes ----------

# as fontes são carregadas a partir dos ficheiros, assim não é preciso instalá-las no windows
$fontes = New-Object System.Drawing.Text.PrivateFontCollection

function Carregar-Fonte($ficheiro) {
    $bytes = [System.IO.File]::ReadAllBytes($ficheiro)
    $memoria = [System.Runtime.InteropServices.Marshal]::AllocCoTaskMem($bytes.Length)
    [System.Runtime.InteropServices.Marshal]::Copy($bytes, 0, $memoria, $bytes.Length)
    $fontes.AddMemoryFont($memoria, $bytes.Length)
    # isto é para a caixa de texto, que desenha as letras de outra forma
    $quantas = 0
    [void][Windows]::AddFontMemResourceEx($memoria, [uint32]$bytes.Length, [IntPtr]::Zero, [ref]$quantas)
}

$letraTexto = New-Object System.Drawing.Font('Consolas', 10)
$letraTitulo = New-Object System.Drawing.Font('Consolas', 14, [System.Drawing.FontStyle]::Bold)

try {
    Carregar-Fonte "$PSScriptRoot\fontes\ShareTechMono-Regular.ttf"
    Carregar-Fonte "$PSScriptRoot\fontes\PressStart2P-Regular.ttf"

    foreach ($familia in $fontes.Families) {
        if ($familia.Name -eq 'Share Tech Mono') {
            $letraTexto = New-Object System.Drawing.Font($familia, 11)
        }
        if ($familia.Name -eq 'Press Start 2P') {
            $letraTitulo = New-Object System.Drawing.Font($familia, 13)
        }
    }
} catch {
    # se as fontes falharem a janela abre na mesma com a Consolas
}

$letraPequena = New-Object System.Drawing.Font($letraTexto.FontFamily, 9.5)

# ---------- php ----------

# usa o comando php se existir, senão vai à pasta onde o winget o instala
function Encontrar-Php {
    $comando = Get-Command php -ErrorAction SilentlyContinue
    if ($comando) {
        return $comando.Source
    }

    $instalado = Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_*\php.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($instalado) {
        return $instalado.FullName
    }
    return $null
}

# o php do winget vem sem php.ini, e sem ele não liga à bd nem faz pedidos às apis
function Php-Configurado($php) {
    if (-not (Test-Path (Join-Path (Split-Path $php) 'php.ini'))) {
        return $false
    }

    $modulos = & $php -m 2>$null
    if ($LASTEXITCODE -ne 0) {
        return $false
    }
    foreach ($extensao in $extensoes) {
        if ($modulos -notcontains $extensao) {
            return $false
        }
    }
    return $true
}

# ---------- janela ----------

$janela = New-Object System.Windows.Forms.Form
$janela.Text = "I'm Gamer"
$janela.Size = New-Object System.Drawing.Size(1180, 780)
$janela.StartPosition = 'CenterScreen'
$janela.WindowState = 'Maximized'
$janela.MinimumSize = New-Object System.Drawing.Size(1000, 720)
$janela.BackColor = $corFundo
$janela.ForeColor = $corTexto
$janela.Font = $letraTexto

if (Test-Path "$PSScriptRoot\imgamer.ico") {
    $janela.Icon = New-Object System.Drawing.Icon("$PSScriptRoot\imgamer.ico")
}

# cabeçalho com o nome
$cabecalho = New-Object System.Windows.Forms.Panel
$cabecalho.Dock = 'Top'
$cabecalho.Height = 76

$titulo = New-Object System.Windows.Forms.Label
$titulo.Text = "I'm Gamer"
$titulo.Font = $letraTitulo
$titulo.AutoSize = $true
$titulo.UseCompatibleTextRendering = $true
$titulo.Location = New-Object System.Drawing.Point(24, 26)

$subtitulo = New-Object System.Windows.Forms.Label
$subtitulo.Text = 'publicar e sincronizar'
$subtitulo.ForeColor = $corDim
$subtitulo.Font = $letraPequena
$subtitulo.AutoSize = $true
$subtitulo.Location = New-Object System.Drawing.Point(210, 30)

# linha fina por baixo do cabeçalho
$linhaCabecalho = New-Object System.Windows.Forms.Panel
$linhaCabecalho.Dock = 'Bottom'
$linhaCabecalho.Height = 1
$linhaCabecalho.BackColor = $corBorda

$cabecalho.Controls.Add($titulo)
$cabecalho.Controls.Add($subtitulo)
$cabecalho.Controls.Add($linhaCabecalho)

# ---------- coluna da esquerda com os botões e o estado ----------

$lateral = New-Object System.Windows.Forms.Panel
$lateral.Dock = 'Left'
$lateral.Width = 340
$lateral.Padding = New-Object System.Windows.Forms.Padding(24, 16, 8, 12)

$menu = New-Object System.Windows.Forms.FlowLayoutPanel
$menu.Dock = 'Fill'
$menu.FlowDirection = 'TopDown'
$menu.WrapContents = $false
$lateral.Controls.Add($menu)

$largura = 290

function Nova-Secao($texto) {
    $secao = New-Object System.Windows.Forms.Label
    $secao.Text = $texto.ToUpper()
    $secao.ForeColor = $corDim
    $secao.Font = $letraPequena
    $secao.AutoSize = $true
    $secao.Margin = New-Object System.Windows.Forms.Padding(0, 14, 0, 8)
    $menu.Controls.Add($secao)
}

function Pintar-Botao($botao, $ativo) {
    if (-not $ativo) {
        $botao.BackColor = $corFundo
        $botao.ForeColor = $corDim
        $botao.Cursor = 'Default'
    } elseif ($botao.Tag -eq 'principal') {
        $botao.BackColor = $corTexto
        $botao.ForeColor = $corFundo
        $botao.Cursor = 'Hand'
    } else {
        $botao.BackColor = $corFundo
        $botao.ForeColor = $corTexto
        $botao.Cursor = 'Hand'
    }
}

function Novo-Botao($texto, $principal) {
    $botao = New-Object System.Windows.Forms.Button
    $botao.Text = $texto
    $botao.Size = New-Object System.Drawing.Size($largura, 40)
    $botao.TextAlign = 'MiddleLeft'
    $botao.Padding = New-Object System.Windows.Forms.Padding(10, 0, 0, 0)
    $botao.Margin = New-Object System.Windows.Forms.Padding(0, 0, 0, 10)
    $botao.FlatStyle = 'Flat'
    $botao.UseCompatibleTextRendering = $true
    $botao.FlatAppearance.BorderColor = $corBorda
    $botao.FlatAppearance.MouseOverBackColor = $corBorda

    if ($principal) {
        $botao.Tag = 'principal'
        $botao.FlatAppearance.BorderSize = 0
        # o botão principal é claro, por isso o rato por cima fica branco e não cinzento
        $botao.FlatAppearance.MouseOverBackColor = [System.Drawing.Color]::White
    }
    Pintar-Botao $botao $true
    $menu.Controls.Add($botao)
    return $botao
}

function Novo-Botao-Pequeno($texto) {
    $botao = New-Object System.Windows.Forms.Button
    $botao.Text = $texto
    $botao.AutoSize = $true
    $botao.AutoSizeMode = 'GrowAndShrink'
    $botao.Font = $letraPequena
    $botao.FlatStyle = 'Flat'
    $botao.UseCompatibleTextRendering = $true
    $botao.FlatAppearance.BorderColor = $corBorda
    $botao.FlatAppearance.MouseOverBackColor = $corBorda
    $botao.Padding = New-Object System.Windows.Forms.Padding(6, 0, 6, 0)
    Pintar-Botao $botao $true
    return $botao
}

Nova-Secao 'Servidor da UA'
$botaoPublicar = Novo-Botao 'Publicar site' $true
$botaoCredenciais = Novo-Botao 'Enviar credenciais' $false
$botaoSite = Novo-Botao 'Abrir site' $false

Nova-Secao 'Neste PC'
$botaoSincronizar = Novo-Botao 'Sincronizar contas' $false
$botaoLocal = Novo-Botao 'Site local' $false

# ---------- à direita, o estado em cima e o registo por baixo ----------

$area = New-Object System.Windows.Forms.Panel
$area.Dock = 'Fill'
$area.Padding = New-Object System.Windows.Forms.Padding(16, 20, 24, 0)

function Novo-Titulo($texto) {
    $rotulo = New-Object System.Windows.Forms.Label
    $rotulo.Text = $texto
    $rotulo.ForeColor = $corDim
    $rotulo.Font = $letraPequena
    $rotulo.AutoSize = $true
    $rotulo.Location = New-Object System.Drawing.Point(0, 8)
    return $rotulo
}

# faixa do estado com a VPN, o PHP e as credenciais lado a lado
$topoEstado = New-Object System.Windows.Forms.Panel
$topoEstado.Dock = 'Top'
$topoEstado.Height = 34
$topoEstado.Controls.Add((Novo-Titulo 'ESTADO'))

$faixaEstado = New-Object System.Windows.Forms.FlowLayoutPanel
$faixaEstado.Dock = 'Top'
$faixaEstado.Height = 44
$faixaEstado.WrapContents = $true

# cada parte do estado tem o nome, o que se passa e um botão para resolver (só aparece quando falta alguma coisa)
function Novo-Estado($nome) {
    $rotulo = New-Object System.Windows.Forms.Label
    $rotulo.Text = $nome
    $rotulo.ForeColor = $corDim
    $rotulo.AutoSize = $true
    $rotulo.Margin = New-Object System.Windows.Forms.Padding(0, 6, 8, 0)

    $valor = New-Object System.Windows.Forms.Label
    $valor.Text = '...'
    $valor.ForeColor = $corDim
    $valor.AutoSize = $true
    $valor.Margin = New-Object System.Windows.Forms.Padding(0, 6, 8, 0)

    $botao = Novo-Botao-Pequeno ''
    $botao.Visible = $false
    $botao.Margin = New-Object System.Windows.Forms.Padding(0, 2, 0, 0)

    # espaço entre as partes do estado
    $espaco = New-Object System.Windows.Forms.Label
    $espaco.Width = 28
    $espaco.Margin = New-Object System.Windows.Forms.Padding(0)

    $faixaEstado.Controls.Add($rotulo)
    $faixaEstado.Controls.Add($valor)
    $faixaEstado.Controls.Add($botao)
    $faixaEstado.Controls.Add($espaco)

    return @{ Valor = $valor; Botao = $botao }
}

$estadoVpn = Novo-Estado 'VPN'
$estadoPhp = Novo-Estado 'PHP'
$estadoCredenciais = Novo-Estado 'Credenciais'

$botaoVerificar = Novo-Botao-Pequeno 'Verificar outra vez'
$topoEstado.Controls.Add($botaoVerificar)

$topoRegisto = New-Object System.Windows.Forms.Panel
$topoRegisto.Dock = 'Top'
$topoRegisto.Height = 34
$topoRegisto.Controls.Add((Novo-Titulo 'REGISTO'))

$botaoLimpar = Novo-Botao-Pequeno 'Limpar'
$topoRegisto.Controls.Add($botaoLimpar)

# o Limpar e o Verificar ficam sempre encostados à direita, mesmo quando a janela muda de tamanho
$topoRegisto.Add_Resize({
    $botaoLimpar.Location = New-Object System.Drawing.Point(($topoRegisto.Width - $botaoLimpar.Width), 2)
})
$topoEstado.Add_Resize({
    $botaoVerificar.Location = New-Object System.Drawing.Point(($topoEstado.Width - $botaoVerificar.Width), 2)
})

$moldura = New-Object System.Windows.Forms.Panel
$moldura.Dock = 'Fill'
$moldura.BackColor = $corBorda
$moldura.Padding = New-Object System.Windows.Forms.Padding(1)

$fundoRegisto = New-Object System.Windows.Forms.Panel
$fundoRegisto.Dock = 'Fill'
$fundoRegisto.BackColor = $corCaixa
$fundoRegisto.Padding = New-Object System.Windows.Forms.Padding(16, 12, 6, 12)

$registo = New-Object System.Windows.Forms.RichTextBox
$registo.Dock = 'Fill'
$registo.ReadOnly = $true
$registo.BorderStyle = 'None'
$registo.BackColor = $corCaixa
$registo.ForeColor = $corTexto
$registo.Font = $letraTexto
$registo.WordWrap = $true
$registo.DetectUrls = $false
# a barra de scroll do windows fica sempre branca, por isso esconde-se e usa-se a de baixo
$registo.ScrollBars = 'None'

# barra de scroll feita à mão, uma calha escura com um puxador que se arrasta
$barra = New-Object System.Windows.Forms.Panel
$barra.Dock = 'Right'
$barra.Width = 10
$barra.BackColor = $corCaixa

$puxador = New-Object System.Windows.Forms.Panel
$puxador.BackColor = $corPuxador
$puxador.Visible = $false
$barra.Controls.Add($puxador)

$fundoRegisto.Controls.Add($registo)
$fundoRegisto.Controls.Add($barra)
$moldura.Controls.Add($fundoRegisto)
# a ordem conta, o que é adicionado primeiro com Dock=Fill fica no meio e o último fica em cima de tudo
$area.Controls.Add($moldura)
$area.Controls.Add($topoRegisto)
$area.Controls.Add($faixaEstado)
$area.Controls.Add($topoEstado)

# barra de baixo com o estado da tarefa
$rodape = New-Object System.Windows.Forms.Panel
$rodape.Dock = 'Bottom'
$rodape.Height = 44
$rodape.Padding = New-Object System.Windows.Forms.Padding(24, 12, 24, 0)

$estado = New-Object System.Windows.Forms.Label
$estado.Dock = 'Fill'
$estado.ForeColor = $corDim
$estado.Font = $letraPequena
$estado.Text = 'Escolhe o que queres fazer.'
$estado.UseCompatibleTextRendering = $true
$rodape.Controls.Add($estado)

$janela.Controls.Add($area)
$janela.Controls.Add($lateral)
$janela.Controls.Add($rodape)
$janela.Controls.Add($cabecalho)

# os botões que ficam parados enquanto uma tarefa corre
$botoesTarefa = @($botaoPublicar, $botaoCredenciais, $botaoSincronizar, $botaoLimpar, $botaoVerificar, $estadoPhp.Botao, $estadoCredenciais.Botao)

# ---------- escrever no registo ----------

function Escrever($texto, $cor, $negrito) {
    $registo.SelectionStart = $registo.TextLength
    $registo.SelectionLength = 0
    $registo.SelectionColor = $cor

    if ($negrito) {
        $registo.SelectionFont = New-Object System.Drawing.Font($letraTexto, [System.Drawing.FontStyle]::Bold)
    } else {
        $registo.SelectionFont = $letraTexto
    }

    $registo.AppendText($texto + "`n")
    $registo.ScrollToCaret()
    Atualizar-Barra
}

# as linhas do robocopy são traduzidas para português e com o caminho mais curto
$tipos = @{
    'New File' = '+ novo      '
    'Newer' = '~ alterado  '
    'Older' = '~ alterado  '
    'Changed' = '~ alterado  '
    'Modified' = '~ alterado  '
    '*EXTRA File' = '- apagado   '
    '*EXTRA Dir' = '- pasta apagada '
    'New Dir' = '+ pasta nova '
}

function Escrever-Linha($linha) {
    if ($linha.Trim() -eq '') {
        return
    }

    # o winget desenha barras de progresso com estes carateres, não interessam no registo
    if ($linha -match '[█▒]' -or $linha -match '^\s*[-\\|/]\s*$') {
        return
    }

    if ($script:atual.Tipo -eq 'robocopy' -and $linha -match '^\s*(New File|Newer|Older|Changed|Modified|\*EXTRA File|\*EXTRA Dir|New Dir)\s+(?:\d+\s+)?(.+)$') {
        $caminho = $matches[2].Trim().Replace($pastaProjeto + '\', '').Replace($servidor + '\', '')
        Escrever ('  ' + $tipos[$matches[1]] + $caminho) $corTexto $false

        if ($matches[1] -like '*EXTRA*') {
            $script:apagados++
        } else {
            $script:enviados++
        }
        return
    }

    if ($linha -match 'ERRO|Fatal error|Warning') {
        Escrever $linha $corTexto $true
    } else {
        Escrever $linha $corTexto $false
    }
}

# ---------- correr os comandos sem bloquear a janela ----------

# cada comando escreve para um ficheiro temporário e um temporizador vai lendo o que aparece
$fila = New-Object System.Collections.Queue
$script:processo = $null
$script:atual = $null
$script:ficheiro = $null
$script:posicao = 0
$script:resto = ''
$script:inicio = $null
$script:tarefa = ''
$script:houveErro = $false
# o que se faz quando a tarefa acaba bem (por exemplo configurar o php depois de o instalar)
$script:depois = $null

# com Enabled = $false o windows pinta o texto dos botões de preto e não se lê no fundo escuro,
# por isso os botões ficam ativos mas ignoram os cliques e são pintados a cinzento à mão
$script:ocupado = $false

function Bloquear-Botoes($bloquear) {
    $script:ocupado = $bloquear
    foreach ($botao in $botoesTarefa) {
        Pintar-Botao $botao (-not $bloquear)
    }
}

function Novo-Comando($tipo, $programa, $argumentos) {
    return @{ Tipo = $tipo; Programa = $programa; Argumentos = $argumentos }
}

function Comecar($nome, $comandos) {
    Bloquear-Botoes $true

    $script:tarefa = $nome
    $script:inicio = Get-Date
    $script:houveErro = $false
    $script:enviados = 0
    $script:apagados = 0

    Escrever '' $corTexto $false
    Escrever ("== $nome (" + (Get-Date -Format 'HH:mm:ss') + ') ==') $corTexto $true

    foreach ($comando in $comandos) {
        $fila.Enqueue($comando)
    }

    Proximo-Comando
    $temporizador.Start()
}

function Proximo-Comando {
    if ($fila.Count -eq 0) {
        Terminar
        return
    }

    $script:atual = $fila.Dequeue()
    Escrever ('> ' + (Split-Path $script:atual.Programa -Leaf) + ' ' + $script:atual.Argumentos) $corDim $false

    $script:ficheiro = [System.IO.Path]::GetTempFileName()
    $script:posicao = 0
    $script:resto = ''

    $script:processo = Start-Process -FilePath $script:atual.Programa -ArgumentList $script:atual.Argumentos `
        -RedirectStandardOutput $script:ficheiro -RedirectStandardError ($script:ficheiro + '.erro') `
        -NoNewWindow -PassThru

    # sem isto o código de saída às vezes vem vazio
    $null = $script:processo.Handle
}

function Ler-Saida {
    if (-not $script:ficheiro -or -not (Test-Path $script:ficheiro)) {
        return
    }

    # o ficheiro está a ser escrito pelo outro programa, por isso abre-se em modo partilhado
    $leitor = [System.IO.File]::Open($script:ficheiro, 'Open', 'Read', 'ReadWrite')
    $leitor.Position = $script:posicao
    $bytes = New-Object byte[] ($leitor.Length - $script:posicao)
    $lidos = $leitor.Read($bytes, 0, $bytes.Length)
    $script:posicao += $lidos
    $leitor.Close()

    if ($lidos -eq 0) {
        return
    }

    # o php e o winget escrevem em utf-8, o robocopy na codificação antiga da consola
    if ($script:atual.Tipo -eq 'robocopy') {
        $texto = [System.Text.Encoding]::GetEncoding(850).GetString($bytes, 0, $lidos)
    } else {
        $texto = [System.Text.Encoding]::UTF8.GetString($bytes, 0, $lidos)
    }

    # a última linha pode ainda não estar completa, fica guardada para a próxima leitura
    $linhas = ($script:resto + $texto) -split "`r`n|`n|`r"
    $script:resto = $linhas[-1]

    for ($i = 0; $i -lt $linhas.Length - 1; $i++) {
        Escrever-Linha $linhas[$i]
    }
}

function Fim-Do-Comando {
    Ler-Saida
    if ($script:resto) {
        Escrever-Linha $script:resto
        $script:resto = ''
    }

    $codigo = $script:processo.ExitCode

    # no robocopy os códigos de 0 a 7 são sucesso, no php só o 0
    # no winget o -1978335189 quer dizer que o programa já estava instalado
    if ($script:atual.Tipo -eq 'robocopy') {
        $falhou = $codigo -ge 8
    } elseif ($script:atual.Tipo -eq 'winget') {
        $falhou = $codigo -ne 0 -and $codigo -ne -1978335189
    } else {
        $falhou = $codigo -ne 0
    }

    if ($falhou) {
        $script:houveErro = $true
        $erros = Get-Content ($script:ficheiro + '.erro') -ErrorAction SilentlyContinue
        foreach ($linha in $erros) {
            Escrever $linha $corTexto $true
        }
        Escrever "  o comando terminou com erro (código $codigo)" $corTexto $true
    }

    Remove-Item $script:ficheiro, ($script:ficheiro + '.erro') -ErrorAction SilentlyContinue
    $script:ficheiro = $null
    $script:processo = $null

    Proximo-Comando
}

function Terminar {
    $temporizador.Stop()
    $segundos = [int]((Get-Date) - $script:inicio).TotalSeconds

    if ($script:tarefa -eq 'Publicar site') {
        if ($script:enviados -eq 0 -and $script:apagados -eq 0 -and -not $script:houveErro) {
            Escrever '  nada para enviar, o servidor já estava atualizado' $corDim $false
        } else {
            Escrever "  $($script:enviados) ficheiros enviados, $($script:apagados) apagados" $corTexto $false
        }
    }

    if ($script:houveErro) {
        $estado.Text = "$($script:tarefa) terminou com erros, vê o registo. ($segundos s)"
    } else {
        $estado.Text = "$($script:tarefa) terminado às " + (Get-Date -Format 'HH:mm') + " ($segundos s)."
    }

    Bloquear-Botoes $false

    $depois = $script:depois
    $script:depois = $null
    if ($depois -and -not $script:houveErro) {
        & $depois
    }
    Atualizar-Estado
}

$temporizador = New-Object System.Windows.Forms.Timer
$temporizador.Interval = 250
$temporizador.Add_Tick({
    if (-not $script:processo) {
        return
    }

    Ler-Saida

    $segundos = [int]((Get-Date) - $script:inicio).TotalSeconds
    $estado.Text = "$($script:tarefa)... $segundos s"

    if ($script:processo.HasExited) {
        Fim-Do-Comando
    }
})

# ---------- vpn, php e credenciais ----------

# tenta ligar ao servidor da UA durante 1,5 s, sem a VPN não responde
# (o Test-Path sozinho pode ficar parado uns 20 s quando a VPN está desligada)
function Tem-Vpn {
    try {
        $cliente = New-Object System.Net.Sockets.TcpClient
        $tentativa = $cliente.BeginConnect('arca.ua.pt', 445, $null, $null)
        $ligou = $tentativa.AsyncWaitHandle.WaitOne(1500) -and $cliente.Connected
        $cliente.Close()
        return $ligou
    } catch {
        return $false
    }
}

function Ha-Vpn {
    if ((Tem-Vpn) -and (Test-Path $servidor)) {
        return $true
    }

    Escrever '' $corTexto $false
    Escrever 'Não consegui chegar ao servidor da UA. Liga a VPN e tenta outra vez.' $corTexto $true
    $estado.Text = 'Sem ligação ao servidor (VPN desligada?).'
    Atualizar-Estado
    return $false
}

function Mostrar-Estado($linha, $texto, $ok, $textoBotao) {
    # o que está bem fica a branco, o que falta fica a cinzento e com o botão para resolver
    $linha.Valor.Text = $texto
    if ($ok) {
        $linha.Valor.ForeColor = $corTexto
        $linha.Botao.Visible = $false
    } else {
        $linha.Valor.ForeColor = $corDim
        $linha.Botao.Text = $textoBotao
        $linha.Botao.Visible = [bool]$textoBotao
    }
}

function Atualizar-Estado {
    $janela.Cursor = 'WaitCursor'

    if (Tem-Vpn) {
        Mostrar-Estado $estadoVpn 'ligada' $true $null
    } else {
        Mostrar-Estado $estadoVpn 'desligada' $false $null
    }

    $php = Encontrar-Php
    if (-not $php) {
        Mostrar-Estado $estadoPhp 'não instalado' $false 'Instalar'
    } elseif (-not (Php-Configurado $php)) {
        Mostrar-Estado $estadoPhp 'falta configurar' $false 'Configurar'
    } else {
        $versao = & $php -r 'echo PHP_VERSION;'
        Mostrar-Estado $estadoPhp $versao $true $null
    }

    if (Test-Path $credenciais) {
        Mostrar-Estado $estadoCredenciais 'neste PC' $true $null
    } else {
        Mostrar-Estado $estadoCredenciais 'em falta' $false 'Trazer'
    }

    $janela.Cursor = 'Default'
}

# cria o php.ini a partir do exemplo que vem com o php, com as extensões e os certificados para o https
function Configurar-Php($perguntar) {
    $php = Encontrar-Php
    if (-not $php) {
        Escrever 'Não encontrei o PHP neste computador.' $corTexto $true
        return
    }

    if (Php-Configurado $php) {
        Escrever 'O PHP já está configurado.' $corDim $false
        return
    }

    if ($perguntar) {
        $resposta = [System.Windows.Forms.MessageBox]::Show(
            "Criar o php.ini com as extensões que o site usa ($($extensoes -join ', ')) e descarregar os certificados do curl?",
            'Configurar o PHP', 'YesNo', 'Question')
        if ($resposta -ne 'Yes') {
            return
        }
    }

    $pasta = Split-Path $php
    $ini = Join-Path $pasta 'php.ini'
    Escrever '' $corTexto $false
    Escrever '== Configurar o PHP ==' $corTexto $true

    try {
        # se já havia um php.ini fica guardado com outro nome
        if (Test-Path $ini) {
            Copy-Item $ini "$ini.antigo" -Force
            Escrever '  o php.ini antigo ficou guardado como php.ini.antigo' $corDim $false
        }

        $texto = [System.IO.File]::ReadAllText((Join-Path $pasta 'php.ini-development'))
        $texto = $texto.Replace(';extension_dir = "ext"', 'extension_dir = "ext"')
        foreach ($extensao in $extensoes) {
            $texto = [regex]::Replace($texto, "(?m)^;extension=$extensao(?=\r?$)", "extension=$extensao")
        }
        $texto = [regex]::Replace($texto, '(?m)^;date\.timezone =[^\r\n]*', 'date.timezone = Europe/Lisbon')

        # sem estes certificados o php não consegue falar com as apis por https
        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
            $ProgressPreference = 'SilentlyContinue'
            Invoke-WebRequest 'https://curl.se/ca/cacert.pem' -OutFile (Join-Path $pasta 'cacert.pem') -UseBasicParsing
            $certificados = (Join-Path $pasta 'cacert.pem').Replace('\', '/')
            $texto = [regex]::Replace($texto, '(?m)^;curl\.cainfo =[^\r\n]*', "curl.cainfo = `"$certificados`"")
            $texto = [regex]::Replace($texto, '(?m)^;openssl\.cafile=[^\r\n]*', "openssl.cafile=`"$certificados`"")
            Escrever '  certificados descarregados do curl.se' $corTexto $false
        } catch {
            Escrever '  não consegui descarregar os certificados, os pedidos às apis por https podem falhar' $corTexto $true
        }

        [System.IO.File]::WriteAllText($ini, $texto)
        Escrever "  php.ini criado em $pasta" $corTexto $false
    } catch {
        Escrever "  não consegui criar o php.ini ($($_.Exception.Message))" $corTexto $true
        return
    }

    if (Php-Configurado $php) {
        Escrever '  PHP pronto' $corTexto $true
    } else {
        Escrever '  o php.ini foi criado mas o PHP ainda não está pronto, vê o que diz o php -m' $corTexto $true
        Escrever '  se o php nem arranca, falta instalar o Microsoft Visual C++ Redistributable (x64)' $corTexto $false
    }
}

# instala o php 8.3 com o winget (fica na pasta do utilizador, não precisa de administrador)
function Resolver-Php {
    if (Encontrar-Php) {
        Configurar-Php $true
        Atualizar-Estado
        return
    }

    if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
        Escrever '' $corTexto $false
        Escrever 'Este PC não tem o winget, por isso o PHP tem de ser instalado à mão.' $corTexto $true
        Escrever '  descarrega o PHP 8.3 (VS16 x64 Thread Safe) em https://windows.php.net/download e põe a pasta no PATH' $corTexto $false
        return
    }

    $resposta = [System.Windows.Forms.MessageBox]::Show(
        'O PHP não está instalado neste PC. Instalar o PHP 8.3 com o winget? Não precisa de administrador.',
        'Instalar o PHP', 'YesNo', 'Question')
    if ($resposta -ne 'Yes') {
        return
    }

    # depois de instalar configura logo, sem perguntar outra vez
    $script:depois = { Configurar-Php $false }
    Comecar 'Instalar o PHP' @(
        (Novo-Comando 'winget' 'winget' 'install --id PHP.PHP.8.3 -e --accept-source-agreements --accept-package-agreements --disable-interactivity')
    )
}

# o credentials.php não vai para o git, num PC novo copia-se o do servidor
function Trazer-Credenciais {
    if (Test-Path $credenciais) {
        Escrever 'O credentials.php já existe neste PC.' $corDim $false
        return
    }

    if (-not (Ha-Vpn)) {
        return
    }

    $resposta = [System.Windows.Forms.MessageBox]::Show(
        'Copiar o credentials.php do servidor da UA para este PC?',
        'Trazer credenciais', 'YesNo', 'Question')
    if ($resposta -ne 'Yes') {
        return
    }

    try {
        Copy-Item "$servidor\projeto\config\credentials.php" $credenciais -ErrorAction Stop
        Escrever '' $corTexto $false
        Escrever 'credentials.php copiado do servidor.' $corTexto $true
    } catch {
        Escrever 'Não foi possível copiar o credentials.php do servidor.' $corTexto $true
    }
    Atualizar-Estado
}

# antes de usar o php vê se está tudo pronto e diz o que falta
function Php-Pronto {
    $php = Encontrar-Php
    if (-not $php -or -not (Php-Configurado $php)) {
        Escrever '' $corTexto $false
        Escrever 'O PHP ainda não está pronto neste PC. Usa o botão ao lado de PHP, em Estado.' $corTexto $true
        Atualizar-Estado
        return $null
    }

    if (-not (Test-Path $credenciais)) {
        Escrever '' $corTexto $false
        Escrever 'Falta o credentials.php neste PC. Usa o botão ao lado de Credenciais, em Estado.' $corTexto $true
        Atualizar-Estado
        return $null
    }
    return $php
}

# ---------- atalho no menu Iniciar ----------

# com o atalho basta carregar na tecla do windows e escrever "gamer"
# se a pasta do projeto mudar de sítio o atalho é corrigido da próxima vez que a janela abre
function Criar-Atalho {
    try {
        $caminho = Join-Path ([Environment]::GetFolderPath('Programs')) "I'm Gamer publicar.lnk"
        $argumentos = "--headless powershell -NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`""

        $shell = New-Object -ComObject WScript.Shell
        $atalho = $shell.CreateShortcut($caminho)
        if ((Test-Path $caminho) -and $atalho.Arguments -eq $argumentos) {
            return
        }

        $atalho.TargetPath = "$env:SystemRoot\System32\conhost.exe"
        $atalho.Arguments = $argumentos
        $atalho.WorkingDirectory = $pastaProjeto
        $atalho.IconLocation = "$PSScriptRoot\imgamer.ico"
        $atalho.Description = 'Publicar o site e sincronizar as contas'
        $atalho.Save()

        Escrever "Criei um atalho no menu Iniciar. Para abrir esta janela carrega na tecla do Windows e escreve gamer." $corDim $false
    } catch {
        # sem atalho continua a dar para abrir pelo publicar.bat
    }
}

# ---------- barra de scroll do registo ----------

# o registo diz quantas linhas tem e qual é a primeira à vista (EM_GETLINECOUNT e EM_GETFIRSTVISIBLELINE)
function Linhas-Registo {
    $total = [int][Windows]::SendMessage($registo.Handle, 0xBA, [IntPtr]::Zero, [IntPtr]::Zero)
    $primeira = [int][Windows]::SendMessage($registo.Handle, 0xCE, [IntPtr]::Zero, [IntPtr]::Zero)
    $visiveis = [Math]::Max(1, [int]($registo.ClientSize.Height / $registo.Font.Height))
    return @($total, $primeira, $visiveis)
}

# anda N linhas para cima (negativo) ou para baixo (EM_LINESCROLL)
function Andar-Registo($linhas) {
    [void][Windows]::SendMessage($registo.Handle, 0xB6, [IntPtr]::Zero, [IntPtr][int]$linhas)
    Atualizar-Barra
}

# o tamanho do puxador mostra quanto do registo está à vista e a posição mostra onde se está
function Atualizar-Barra {
    $total, $primeira, $visiveis = Linhas-Registo
    if ($total -le $visiveis) {
        $puxador.Visible = $false
        return
    }

    $tamanho = [Math]::Max(30, [int]($barra.Height * $visiveis / $total))
    $topo = [int](($barra.Height - $tamanho) * $primeira / ($total - $visiveis))
    $topo = [Math]::Min($topo, $barra.Height - $tamanho)
    $puxador.SetBounds(2, $topo, 6, $tamanho)
    $puxador.Visible = $true
}

# sem a barra do windows a roda do rato deixa de mexer no registo, por isso faz-se aqui (3 linhas por volta)
$registo.Add_MouseWheel({
    param($origem, $evento)
    Andar-Registo (-[int]($evento.Delta / 120) * 3)
})
$registo.Add_VScroll({ Atualizar-Barra })
$registo.Add_Resize({ Atualizar-Barra })

# arrastar o puxador
$puxador.Add_MouseDown({
    param($origem, $evento)
    $script:pegouEm = $evento.Y
})
$puxador.Add_MouseMove({
    param($origem, $evento)
    if ($evento.Button -ne 'Left') {
        return
    }

    $total, $primeira, $visiveis = Linhas-Registo
    $livre = $barra.Height - $puxador.Height
    if ($livre -le 0) {
        return
    }

    $novoTopo = [Math]::Max(0, [Math]::Min($livre, $puxador.Top + $evento.Y - $script:pegouEm))
    $alvo = [int](($total - $visiveis) * $novoTopo / $livre)
    Andar-Registo ($alvo - $primeira)
})

# clicar na calha por cima ou por baixo do puxador anda uma página
$barra.Add_MouseDown({
    param($origem, $evento)
    $total, $primeira, $visiveis = Linhas-Registo
    if ($evento.Y -lt $puxador.Top) {
        Andar-Registo (-$visiveis)
    } else {
        Andar-Registo $visiveis
    }
})

# o puxador fica mais claro com o rato por cima
$puxador.Add_MouseEnter({ $puxador.BackColor = $corDim })
$puxador.Add_MouseLeave({ $puxador.BackColor = $corPuxador })

# ---------- botões ----------

$botaoPublicar.Add_Click({
    # enquanto uma tarefa está a correr os botões não fazem nada
    if ($script:ocupado) {
        return
    }

    if (-not (Ha-Vpn)) {
        return
    }

    # o /MIR deixa as pastas do servidor iguais às daqui, o credentials.php do servidor nunca é tocado
    $opcoes = '/NJH /NJS /NDL /NP /FP'
    Comecar 'Publicar site' @(
        (Novo-Comando 'robocopy' 'robocopy' "`"$pastaProjeto\common`" `"$servidor\common`" /MIR $opcoes"),
        (Novo-Comando 'robocopy' 'robocopy' "`"$pastaProjeto\projeto`" `"$servidor\projeto`" /MIR /XF credentials.php $opcoes"),
        (Novo-Comando 'robocopy' 'robocopy' "`"$pastaProjeto`" `"$servidor`" index.html $opcoes")
    )
})

$botaoSincronizar.Add_Click({
    if ($script:ocupado) {
        return
    }

    if (-not (Ha-Vpn)) {
        return
    }

    $php = Php-Pronto
    if (-not $php) {
        return
    }

    Comecar 'Sincronizar contas' @(
        (Novo-Comando 'php' $php "`"$pastaProjeto\projeto\tarefas\sincronizar.php`"")
    )
})

# o "Publicar site" nunca toca no credentials.php, para não se substituírem as passwords do servidor sem querer
# este botão é só para quando se muda uma chave e pede confirmação antes
$botaoCredenciais.Add_Click({
    if ($script:ocupado) {
        return
    }

    if (-not (Test-Path $credenciais)) {
        Escrever 'Não há credentials.php neste PC para enviar.' $corTexto $true
        return
    }

    if (-not (Ha-Vpn)) {
        return
    }

    $resposta = [System.Windows.Forms.MessageBox]::Show(
        'Isto substitui o credentials.php do servidor pelo deste computador. Continuar?',
        'Enviar credenciais', 'YesNo', 'Warning')

    if ($resposta -ne 'Yes') {
        return
    }

    try {
        Copy-Item $credenciais "$servidor\projeto\config\credentials.php" -ErrorAction Stop
        Escrever '' $corTexto $false
        Escrever 'credentials.php enviado para o servidor.' $corTexto $true
        $estado.Text = 'Credenciais enviadas às ' + (Get-Date -Format 'HH:mm') + '.'
    } catch {
        Escrever 'Não foi possível enviar o credentials.php.' $corTexto $true
    }
})

$botaoSite.Add_Click({
    Start-Process $site
})

# o servidor da UA não tem internet, por isso coisas como ligar a Epic só funcionam com o site a correr aqui no PC
# arranca o php como servidor (se ainda não estiver ligado) e abre a página das contas ligadas
$botaoLocal.Add_Click({
    if (-not (Ha-Vpn)) {
        return
    }

    $php = Php-Pronto
    if (-not $php) {
        return
    }

    # vê se já há alguma coisa a responder na porta 8124
    $ligado = $false
    try {
        $teste = New-Object System.Net.Sockets.TcpClient('localhost', 8124)
        $teste.Close()
        $ligado = $true
    } catch {
    }

    # 127.0.0.1 e não localhost, senão o php fica só em ipv6 e o emulador (10.0.2.2) não chega lá
    if (-not $ligado) {
        $script:siteLocal = Start-Process -FilePath $php -ArgumentList "-S 127.0.0.1:8124 -t `"$pastaProjeto`"" -WindowStyle Hidden -PassThru
        Start-Sleep -Milliseconds 800
    }

    Start-Process 'http://localhost:8124/projeto/contas/contas.php'
    Escrever '' $corTexto $false
    Escrever 'Site local aberto em http://localhost:8124 (fecha quando fechares esta janela).' $corTexto $true
    $estado.Text = 'Site local a correr.'
})

$botaoLimpar.Add_Click({
    if ($script:ocupado) {
        return
    }

    $registo.Clear()
    $estado.Text = 'Escolhe o que queres fazer.'
})

$botaoVerificar.Add_Click({
    if ($script:ocupado) {
        return
    }
    Atualizar-Estado
})

$estadoPhp.Botao.Add_Click({
    if ($script:ocupado) {
        return
    }
    Resolver-Php
})

$estadoCredenciais.Botao.Add_Click({
    if ($script:ocupado) {
        return
    }
    Trazer-Credenciais
})

# barra de título escura (windows 10 e 11)
$janela.Add_HandleCreated({
    $escuro = 1
    [void][Windows]::DwmSetWindowAttribute($janela.Handle, 20, [ref]$escuro, 4)
})

# o estado só é visto depois de a janela aparecer, para ela não demorar a abrir
$janela.Add_Shown({
    Escrever 'A ver o que está pronto neste PC...' $corDim $false
    Atualizar-Estado
    Criar-Atalho
    $janela.Activate()
})

# ao fechar a janela desliga o site local, se foi esta janela que o ligou
$janela.Add_FormClosed({
    if ($script:siteLocal -and -not $script:siteLocal.HasExited) {
        Stop-Process -Id $script:siteLocal.Id -Force
    }
})

[void]$janela.ShowDialog()
