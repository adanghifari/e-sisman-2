$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$commands = @(
    @{ Name = 'server'; Command = 'php artisan serve --host=127.0.0.1 --port=8008' },
    @{ Name = 'queue'; Command = 'php artisan queue:listen --tries=1' },
    @{ Name = 'assets'; Command = 'npm run watch' }
)

Write-Host 'Starting E-SISMAN dev services...'
Write-Host 'Laravel: http://127.0.0.1:8008'
Write-Host 'Press Ctrl+C to stop all services.'

$jobs = foreach ($item in $commands) {
    Start-Job -Name $item.Name -ArgumentList $root, $item.Name, $item.Command -ScriptBlock {
        param ($root, $name, $command)

        Set-Location $root
        & cmd.exe /d /s /c $command 2>&1 | ForEach-Object {
            "[$name] $_"
        }

        exit $LASTEXITCODE
    }
}

try {
    while (($jobs | Where-Object { $_.State -eq 'Running' }).Count -gt 0) {
        foreach ($job in $jobs) {
            Receive-Job -Job $job
        }

        Start-Sleep -Milliseconds 500
    }

    foreach ($job in $jobs) {
        Receive-Job -Job $job
    }

    $failed = $jobs | Where-Object { $_.State -eq 'Failed' -or $_.ChildJobs[0].JobStateInfo.State -eq 'Failed' }
    if ($failed.Count -gt 0) {
        exit 1
    }
} finally {
    foreach ($job in $jobs) {
        if ($job.State -eq 'Running') {
            Stop-Job -Job $job
        }

        Remove-Job -Job $job -Force
    }
}
