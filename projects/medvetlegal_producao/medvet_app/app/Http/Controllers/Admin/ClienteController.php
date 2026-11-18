<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $busca = $request->query('busca');

        $clientes = Cliente::withCount('tickets')
            ->when($busca, function ($query, $busca) {
                $query->where(function ($q) use ($busca) {
                    $q->where('nome', 'like', "%{$busca}%")
                      ->orWhere('email', 'like', "%{$busca}%")
                      ->orWhere('cpf_cnpj', 'like', "%{$busca}%");
                });
            })
            ->orderBy('nome')
            ->paginate(20)
            ->withQueryString();

        return view('admin.clientes.index', compact('clientes', 'busca'));
    }

    public function show(Cliente $cliente)
    {
        $cliente->load(['tickets' => function ($query) {
            $query->orderByDesc('created_at');
        }]);

        return view('admin.clientes.show', compact('cliente'));
    }

    public function create()
    {
        return view('admin.clientes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:clientes,email',
            'telefone' => 'nullable|string|max:20',
            'cpf_cnpj' => 'nullable|string|max:20',
        ]);

        Cliente::create($validated);

        return redirect()->route('admin.clientes.index')->with('success', 'Cliente cadastrado com sucesso!');
    }

    public function edit(Cliente $cliente)
    {
        return view('admin.clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('clientes', 'email')->ignore($cliente->id)],
            'telefone' => 'nullable|string|max:20',
            'cpf_cnpj' => 'nullable|string|max:20',
        ]);

        $cliente->update($validated);

        return redirect()->route('admin.clientes.index')->with('success', 'Cliente atualizado com sucesso!');
    }

    public function destroy(Cliente $cliente)
    {
        if ($cliente->tickets()->exists()) {
            return back()->with('error', 'Este cliente possui tickets vinculados e não pode ser excluído.');
        }

        $cliente->delete();

        return redirect()->route('admin.clientes.index')->with('success', 'Cliente excluído com sucesso!');
    }
}
