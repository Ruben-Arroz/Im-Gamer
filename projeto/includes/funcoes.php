<?php

function formatarHoras(int $minutos): string
{
    return number_format($minutos / 60, 0, ',', ' ') . ' h';
}
