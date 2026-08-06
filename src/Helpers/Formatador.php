<?php

namespace MsCell\Helpers;

class Formatador
{
    public static function moeda(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    public static function data(string $dataHora): string
    {
        return date('d/m/Y H:i', strtotime($dataHora));
    }

    public static function papel(string $papel): string
    {
        return match ($papel) {
            'admin' => 'Administrador',
            'funcionario' => 'Funcionário',
            'usuario' => 'Usuário',
            default => ucfirst($papel),
        };
    }
}
