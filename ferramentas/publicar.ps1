# janela para publicar o site no servidor da UA e sincronizar as contas (precisa da VPN ligada)
# abre-se com o publicar.bat que está na raiz do projeto

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

# funções do windows para usar as fontes do site e pôr a barra de título e o scroll escuros
Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
public class Windows {
    [DllImport("gdi32.dll")]
    public static extern IntPtr AddFontMemResourceEx(IntPtr dados, uint tamanho, IntPtr reservado, ref uint quantas);
    [DllImport("uxtheme.dll", CharSet = CharSet.Unicode)]
    public static extern int SetWindowTheme(IntPtr janela, string tema, string lista);
    [DllImport("dwmapi.dll")]
    public static extern int DwmSetWindowAttribute(IntPtr janela, int atributo, ref int valor, int tamanho);
    [DllImport("uxtheme.dll", EntryPoint = "#135")]
    public static extern int SetPreferredAppMode(int modo);
}
'@

# sem isto o windows ignora o tema escuro no scroll (2 = forçar escuro)
[void][Windows]::SetPreferredAppMode(2)

$pastaProjeto = Split-Path $PSScriptRoot -Parent
$servidor = '\\arca.ua.pt\Hosting\esan-tesp-ds-paw.web.ua.pt\tesp-ds-g17'
$site = 'https://esan-tesp-ds-paw.web.ua.pt/tesp-ds-g17/projeto/'

# usa o comando php se existir, senão vai à pasta onde o winget o instalou
$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $php) {
    $php = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
}

# cores iguais às do site (common/style.css)
$corFundo = [System.Drawing.Color]::FromArgb(14, 15, 18)
$corCaixa = [System.Drawing.Color]::FromArgb(22, 23, 27)
$corBorda = [System.Drawing.Color]::FromArgb(36, 38, 43)
$corTexto = [System.Drawing.Color]::FromArgb(242, 242, 240)
$corDim = [System.Drawing.Color]::FromArgb(143, 145, 152)

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

# ---------- janela ----------

$janela = New-Object System.Windows.Forms.Form
$janela.Text = "I'm Gamer"
$janela.WindowState = 'Maximized'
$janela.MinimumSize = New-Object System.Drawing.Size(720, 480)
$janela.BackColor = $corFundo
$janela.ForeColor = $corTexto
$janela.Font = $letraTexto

$logo = "$pastaProjeto\projeto\assets\img\logos\versao3\logo_branco_fundo_preto.png"
if (Test-Path $logo) {
    $imagem = New-Object System.Drawing.Bitmap($logo)
    $janela.Icon = [System.Drawing.Icon]::FromHandle($imagem.GetHicon())
}

# cabeçalho com o nome e os botões
$cabecalho = New-Object System.Windows.Forms.Panel
$cabecalho.Dock = 'Top'
$cabecalho.Height = 128
$cabecalho.Padding = New-Object System.Windows.Forms.Padding(24, 20, 24, 0)

$titulo = New-Object System.Windows.Forms.Label
$titulo.Text = "I'm Gamer"
$titulo.Font = $letraTitulo
$titulo.AutoSize = $true
$titulo.UseCompatibleTextRendering = $true
$titulo.Location = New-Object System.Drawing.Point(24, 22)

$subtitulo = New-Object System.Windows.Forms.Label
$subtitulo.Text = 'publicar e sincronizar'
$subtitulo.ForeColor = $corDim
$subtitulo.Font = $letraPequena
$subtitulo.AutoSize = $true
$subtitulo.Location = New-Object System.Drawing.Point(210, 26)

# os botões ficam numa "fila" que passa para a linha de baixo se a janela for estreita
$botoes = New-Object System.Windows.Forms.FlowLayoutPanel
$botoes.Location = New-Object System.Drawing.Point(20, 64)
$botoes.Size = New-Object System.Drawing.Size(1200, 50)
$botoes.Anchor = 'Top, Left, Right'
$botoes.WrapContents = $true

function Novo-Botao($texto, $principal) {
    $botao = New-Object System.Windows.Forms.Button
    $botao.Text = $texto
    $botao.AutoSize = $true
    $botao.AutoSizeMode = 'GrowAndShrink'
    $botao.Padding = New-Object System.Windows.Forms.Padding(14, 6, 14, 6)
    $botao.Margin = New-Object System.Windows.Forms.Padding(4, 0, 8, 8)
    $botao.FlatStyle = 'Flat'
    $botao.Cursor = 'Hand'
    $botao.UseCompatibleTextRendering = $true

    if ($principal) {
        $botao.BackColor = $corTexto
        $botao.ForeColor = $corFundo
        $botao.FlatAppearance.BorderSize = 0
    } else {
        $botao.BackColor = $corFundo
        $botao.ForeColor = $corTexto
        $botao.FlatAppearance.BorderColor = $corBorda
    }

    $botao.FlatAppearance.MouseOverBackColor = $corBorda
    $botoes.Controls.Add($botao)
    return $botao
}

$botaoPublicar = Novo-Botao 'Publicar site' $true
$botaoSincronizar = Novo-Botao 'Sincronizar contas' $false
$botaoCredenciais = Novo-Botao 'Enviar credenciais' $false
$botaoSite = Novo-Botao 'Abrir site' $false
$botaoLocal = Novo-Botao 'Site local' $false
$botaoLimpar = Novo-Botao 'Limpar' $false

# o botão principal fica com texto escuro quando o rato passa por cima
$botaoPublicar.FlatAppearance.MouseOverBackColor = [System.Drawing.Color]::White

# caixa com o registo do que vai acontecendo
$area = New-Object System.Windows.Forms.Panel
$area.Dock = 'Fill'
$area.Padding = New-Object System.Windows.Forms.Padding(24, 4, 24, 0)

$moldura = New-Object System.Windows.Forms.Panel
$moldura.Dock = 'Fill'
$moldura.BackColor = $corBorda
$moldura.Padding = New-Object System.Windows.Forms.Padding(1)

$fundoRegisto = New-Object System.Windows.Forms.Panel
$fundoRegisto.Dock = 'Fill'
$fundoRegisto.BackColor = $corCaixa
$fundoRegisto.Padding = New-Object System.Windows.Forms.Padding(16, 12, 4, 12)

$registo = New-Object System.Windows.Forms.RichTextBox
$registo.Dock = 'Fill'
$registo.ReadOnly = $true
$registo.BorderStyle = 'None'
$registo.BackColor = $corCaixa
$registo.ForeColor = $corTexto
$registo.Font = $letraTexto
$registo.WordWrap = $true
$registo.DetectUrls = $false

$fundoRegisto.Controls.Add($registo)
$moldura.Controls.Add($fundoRegisto)
$area.Controls.Add($moldura)

# barra de baixo com o estado
$rodape = New-Object System.Windows.Forms.Panel
$rodape.Dock = 'Bottom'
$rodape.Height = 44
$rodape.Padding = New-Object System.Windows.Forms.Padding(24, 12, 24, 0)

$estado = New-Object System.Windows.Forms.Label
$estado.Dock = 'Fill'
$estado.ForeColor = $corDim
$estado.Font = $letraPequena
$estado.Text = 'Liga a VPN e escolhe o que queres fazer.'
$estado.UseCompatibleTextRendering = $true
$rodape.Controls.Add($estado)

$cabecalho.Controls.Add($titulo)
$cabecalho.Controls.Add($subtitulo)
$cabecalho.Controls.Add($botoes)

# a ordem conta: o que é adicionado primeiro com Dock=Fill fica no meio
$janela.Controls.Add($area)
$janela.Controls.Add($rodape)
$janela.Controls.Add($cabecalho)

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

# com Enabled = $false o windows pinta o texto dos botões de preto e não se lê no fundo escuro,
# por isso os botões ficam ativos mas ignoram os cliques e são pintados a cinzento à mão
$script:ocupado = $false

function Bloquear-Botoes($bloquear) {
    $script:ocupado = $bloquear

    foreach ($botao in @($botaoPublicar, $botaoSincronizar, $botaoCredenciais, $botaoLimpar)) {
        if ($bloquear) {
            $botao.BackColor = $corFundo
            $botao.ForeColor = $corDim
            $botao.Cursor = 'Default'
        } elseif ($botao -eq $botaoPublicar) {
            $botao.BackColor = $corTexto
            $botao.ForeColor = $corFundo
            $botao.Cursor = 'Hand'
        } else {
            $botao.BackColor = $corFundo
            $botao.ForeColor = $corTexto
            $botao.Cursor = 'Hand'
        }
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

    # o php escreve em utf-8, o robocopy na codificação antiga da consola
    if ($script:atual.Tipo -eq 'php') {
        $texto = [System.Text.Encoding]::UTF8.GetString($bytes, 0, $lidos)
    } else {
        $texto = [System.Text.Encoding]::GetEncoding(850).GetString($bytes, 0, $lidos)
    }

    # a última linha pode ainda não estar completa, fica guardada para a próxima leitura
    $linhas = ($script:resto + $texto) -split "`r?`n"
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
    $falhou = ($script:atual.Tipo -eq 'robocopy' -and $codigo -ge 8) -or ($script:atual.Tipo -eq 'php' -and $codigo -ne 0)

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

# ---------- botões ----------

function Ha-Vpn {
    if (Test-Path $servidor) {
        return $true
    }

    Escrever '' $corTexto $false
    Escrever 'Não consegui chegar ao servidor da UA. Liga a VPN e tenta outra vez.' $corTexto $true
    $estado.Text = 'Sem ligação ao servidor (VPN desligada?).'
    return $false
}

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
    # enquanto uma tarefa está a correr os botões não fazem nada
    if ($script:ocupado) {
        return
    }

    if (-not (Ha-Vpn)) {
        return
    }

    if (-not (Test-Path $php)) {
        Escrever 'Não encontrei o PHP neste computador.' $corTexto $true
        return
    }

    Comecar 'Sincronizar contas' @(
        (Novo-Comando 'php' $php "`"$pastaProjeto\projeto\sincronizar.php`"")
    )
})

# o "Publicar site" nunca toca no credentials.php, para não se substituírem as passwords do servidor sem querer
# este botão é só para quando se muda uma chave e pede confirmação antes
$botaoCredenciais.Add_Click({
    if ($script:ocupado) {
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
        Copy-Item "$pastaProjeto\projeto\config\credentials.php" "$servidor\projeto\config\credentials.php" -ErrorAction Stop
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

    if (-not (Test-Path $php)) {
        Escrever 'Não encontrei o PHP neste computador.' $corTexto $true
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

    if (-not $ligado) {
        $script:siteLocal = Start-Process -FilePath $php -ArgumentList "-S localhost:8124 -t `"$pastaProjeto`"" -WindowStyle Hidden -PassThru
        Start-Sleep -Milliseconds 800
    }

    Start-Process 'http://localhost:8124/projeto/contas.php'
    Escrever '' $corTexto $false
    Escrever 'Site local aberto em http://localhost:8124 (fecha quando fechares esta janela).' $corTexto $true
    $estado.Text = 'Site local a correr.'
})

$botaoLimpar.Add_Click({
    # enquanto uma tarefa está a correr os botões não fazem nada
    if ($script:ocupado) {
        return
    }

    $registo.Clear()
    $estado.Text = 'Liga a VPN e escolhe o que queres fazer.'
})

# barra de título e scroll escuros (windows 10 e 11)
$janela.Add_HandleCreated({
    $escuro = 1
    [void][Windows]::DwmSetWindowAttribute($janela.Handle, 20, [ref]$escuro, 4)
})
$registo.Add_HandleCreated({
    [void][Windows]::SetWindowTheme($registo.Handle, 'DarkMode_Explorer', $null)
})

# ao fechar a janela desliga o site local, se foi esta janela que o ligou
$janela.Add_FormClosed({
    if ($script:siteLocal -and -not $script:siteLocal.HasExited) {
        Stop-Process -Id $script:siteLocal.Id -Force
    }
})

[void]$janela.ShowDialog()
