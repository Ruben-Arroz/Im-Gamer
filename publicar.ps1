# janela simples para enviar o site para o servidor da UA (precisa da VPN ligada)
Add-Type -AssemblyName System.Windows.Forms

$pasta = $PSScriptRoot
$servidor = '\\arca.ua.pt\Hosting\esan-tesp-ds-paw.web.ua.pt\tesp-ds-g17'

$janela = New-Object System.Windows.Forms.Form
$janela.Text = "Publicar I'm Gamer"
$janela.Size = New-Object System.Drawing.Size(600, 440)
$janela.StartPosition = 'CenterScreen'

$botao = New-Object System.Windows.Forms.Button
$botao.Text = 'Publicar'
$botao.Location = New-Object System.Drawing.Point(12, 12)
$botao.Size = New-Object System.Drawing.Size(120, 32)

$estado = New-Object System.Windows.Forms.Label
$estado.Text = 'Liga a VPN e carrega em Publicar.'
$estado.Location = New-Object System.Drawing.Point(145, 20)
$estado.Size = New-Object System.Drawing.Size(420, 20)

$registo = New-Object System.Windows.Forms.TextBox
$registo.Multiline = $true
$registo.ReadOnly = $true
$registo.ScrollBars = 'Vertical'
$registo.Font = New-Object System.Drawing.Font('Consolas', 9)
$registo.Location = New-Object System.Drawing.Point(12, 56)
$registo.Size = New-Object System.Drawing.Size(560, 330)

$botao.Add_Click({
    $botao.Enabled = $false
    $registo.Clear()

    if (-not (Test-Path $servidor)) {
        $estado.Text = 'Não consegui chegar ao servidor. A VPN está ligada?'
        $botao.Enabled = $true
        return
    }

    $estado.Text = 'A publicar...'
    $janela.Refresh()

    # o /MIR deixa as pastas do servidor iguais às daqui, o credentials.php do servidor nunca é tocado
    $linhas = @()
    $erro = $false

    $linhas += robocopy "$pasta\common" "$servidor\common" /MIR /NJH /NJS /NDL /NP
    if ($LASTEXITCODE -ge 8) { $erro = $true }

    $linhas += robocopy "$pasta\projeto" "$servidor\projeto" /MIR /XF credentials.php /NJH /NJS /NDL /NP
    if ($LASTEXITCODE -ge 8) { $erro = $true }

    $linhas += robocopy $pasta $servidor index.html /NJH /NJS /NDL /NP
    if ($LASTEXITCODE -ge 8) { $erro = $true }

    $alteracoes = $linhas | Where-Object { $_.Trim() -ne '' } | ForEach-Object { $_.Trim() }

    if ($alteracoes) {
        $registo.Text = $alteracoes -join "`r`n"
    } else {
        $registo.Text = 'Nada para enviar, o servidor já está atualizado.'
    }

    if ($erro) {
        $estado.Text = 'Houve erros, vê a lista em baixo.'
    } else {
        $estado.Text = 'Site atualizado às ' + (Get-Date -Format 'HH:mm') + '.'
    }

    $botao.Enabled = $true
})

$janela.Controls.Add($botao)
$janela.Controls.Add($estado)
$janela.Controls.Add($registo)
[void]$janela.ShowDialog()
