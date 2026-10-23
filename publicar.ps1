# janela simples para enviar o site para o servidor da UA (precisa da VPN ligada)
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

$pasta = $PSScriptRoot
$servidor = '\\arca.ua.pt\Hosting\esan-tesp-ds-paw.web.ua.pt\tesp-ds-g17'

# cores iguais às do site
$fundo = [System.Drawing.Color]::FromArgb(14, 15, 18)
$borda = [System.Drawing.Color]::FromArgb(36, 38, 43)
$texto = [System.Drawing.Color]::FromArgb(242, 242, 240)
$textoDim = [System.Drawing.Color]::FromArgb(143, 145, 152)

$janela = New-Object System.Windows.Forms.Form
$janela.Text = "Publicar I'm Gamer"
$janela.WindowState = 'Maximized'
$janela.BackColor = $fundo
$janela.ForeColor = $texto
$janela.Font = New-Object System.Drawing.Font('Consolas', 11)

$topo = New-Object System.Windows.Forms.Panel
$topo.Dock = 'Top'
$topo.Height = 70
$topo.Padding = New-Object System.Windows.Forms.Padding(20, 16, 20, 0)

$botao = New-Object System.Windows.Forms.Button
$botao.Text = 'Publicar'
$botao.Location = New-Object System.Drawing.Point(20, 16)
$botao.Size = New-Object System.Drawing.Size(140, 38)
$botao.FlatStyle = 'Flat'
$botao.BackColor = $texto
$botao.ForeColor = $fundo
$botao.FlatAppearance.BorderSize = 0
$botao.Cursor = 'Hand'

$estado = New-Object System.Windows.Forms.Label
$estado.Text = 'Liga a VPN e carrega em Publicar.'
$estado.Location = New-Object System.Drawing.Point(180, 25)
$estado.AutoSize = $true
$estado.ForeColor = $textoDim

$registo = New-Object System.Windows.Forms.TextBox
$registo.Multiline = $true
$registo.ReadOnly = $true
$registo.ScrollBars = 'Vertical'
$registo.Dock = 'Fill'
$registo.BackColor = $fundo
$registo.ForeColor = $texto
$registo.BorderStyle = 'None'

# margem à volta da caixa de texto
$area = New-Object System.Windows.Forms.Panel
$area.Dock = 'Fill'
$area.Padding = New-Object System.Windows.Forms.Padding(20)
$area.BackColor = $fundo

$caixa = New-Object System.Windows.Forms.Panel
$caixa.Dock = 'Fill'
$caixa.Padding = New-Object System.Windows.Forms.Padding(12)
$caixa.BackColor = $borda

$caixa.Controls.Add($registo)
$area.Controls.Add($caixa)

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

$topo.Controls.Add($botao)
$topo.Controls.Add($estado)
$janela.Controls.Add($area)
$janela.Controls.Add($topo)
[void]$janela.ShowDialog()
