<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AddressController extends Controller
{
    /**
     * Lista todos os estados brasileiros
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStates()
    {
        try {
            // Cache por 24 horas (estados não mudam frequentemente)
            $states = Cache::remember('brazilian_states', 86400, function () {
                $response = Http::get('https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome');
                
                if ($response->successful()) {
                    return collect($response->json())->map(function ($state) {
                        return [
                            'id' => $state['id'],
                            'sigla' => $state['sigla'],
                            'nome' => $state['nome'],
                        ];
                    })->toArray();
                }
                
                // Fallback para lista estática
                return $this->getFallbackStates();
            });
            
            return response()->json([
                'success' => true,
                'data' => $states,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar estados',
                'data' => $this->getFallbackStates(),
            ], 500);
        }
    }
    
    /**
     * Lista cidades de um estado específico
     * 
     * @param string $uf Sigla do estado (ex: SP, RJ)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCities($uf)
    {
        try {
            // Valida UF
            $uf = strtoupper($uf);
            if (strlen($uf) !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'UF inválida',
                    'data' => [],
                ], 400);
            }
            
            // Cache por 24 horas
            $cities = Cache::remember("cities_{$uf}", 86400, function () use ($uf) {
                $response = Http::get("https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$uf}/municipios?orderBy=nome");
                
                if ($response->successful()) {
                    return collect($response->json())->map(function ($city) {
                        return [
                            'id' => $city['id'],
                            'nome' => $city['nome'],
                        ];
                    })->toArray();
                }
                
                return [];
            });
            
            return response()->json([
                'success' => true,
                'data' => $cities,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar cidades',
                'data' => [],
            ], 500);
        }
    }
    
    /**
     * Busca endereço por CEP
     * 
     * @param string $cep CEP (com ou sem máscara)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAddressByCep($cep)
    {
        try {
            // Remove máscara do CEP
            $cleanCep = preg_replace('/\D/', '', $cep);
            
            // Valida CEP
            if (strlen($cleanCep) !== 8) {
                return response()->json([
                    'success' => false,
                    'message' => 'CEP inválido',
                    'data' => null,
                ], 400);
            }
            
            // Cache por 30 dias (CEPs não mudam)
            $address = Cache::remember("cep_{$cleanCep}", 2592000, function () use ($cleanCep) {
                $response = Http::get("https://viacep.com.br/ws/{$cleanCep}/json/");
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    // ViaCEP retorna { erro: true } quando CEP não existe
                    if (isset($data['erro']) && $data['erro']) {
                        return null;
                    }
                    
                    return [
                        'cep' => $data['cep'] ?? '',
                        'address' => $data['logradouro'] ?? '',
                        'neighborhood' => $data['bairro'] ?? '',
                        'city' => $data['localidade'] ?? '',
                        'state' => $data['uf'] ?? '',
                        'complement' => $data['complemento'] ?? '',
                    ];
                }
                
                return null;
            });
            
            if (!$address) {
                return response()->json([
                    'success' => false,
                    'message' => 'CEP não encontrado',
                    'data' => null,
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $address,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar CEP',
                'data' => null,
            ], 500);
        }
    }
    
    /**
     * Lista estática de estados (fallback)
     * 
     * @return array
     */
    private function getFallbackStates()
    {
        return [
            ['id' => 12, 'sigla' => 'AC', 'nome' => 'Acre'],
            ['id' => 27, 'sigla' => 'AL', 'nome' => 'Alagoas'],
            ['id' => 16, 'sigla' => 'AP', 'nome' => 'Amapá'],
            ['id' => 13, 'sigla' => 'AM', 'nome' => 'Amazonas'],
            ['id' => 29, 'sigla' => 'BA', 'nome' => 'Bahia'],
            ['id' => 23, 'sigla' => 'CE', 'nome' => 'Ceará'],
            ['id' => 53, 'sigla' => 'DF', 'nome' => 'Distrito Federal'],
            ['id' => 32, 'sigla' => 'ES', 'nome' => 'Espírito Santo'],
            ['id' => 52, 'sigla' => 'GO', 'nome' => 'Goiás'],
            ['id' => 21, 'sigla' => 'MA', 'nome' => 'Maranhão'],
            ['id' => 51, 'sigla' => 'MT', 'nome' => 'Mato Grosso'],
            ['id' => 50, 'sigla' => 'MS', 'nome' => 'Mato Grosso do Sul'],
            ['id' => 31, 'sigla' => 'MG', 'nome' => 'Minas Gerais'],
            ['id' => 15, 'sigla' => 'PA', 'nome' => 'Pará'],
            ['id' => 25, 'sigla' => 'PB', 'nome' => 'Paraíba'],
            ['id' => 41, 'sigla' => 'PR', 'nome' => 'Paraná'],
            ['id' => 26, 'sigla' => 'PE', 'nome' => 'Pernambuco'],
            ['id' => 22, 'sigla' => 'PI', 'nome' => 'Piauí'],
            ['id' => 33, 'sigla' => 'RJ', 'nome' => 'Rio de Janeiro'],
            ['id' => 24, 'sigla' => 'RN', 'nome' => 'Rio Grande do Norte'],
            ['id' => 43, 'sigla' => 'RS', 'nome' => 'Rio Grande do Sul'],
            ['id' => 11, 'sigla' => 'RO', 'nome' => 'Rondônia'],
            ['id' => 14, 'sigla' => 'RR', 'nome' => 'Roraima'],
            ['id' => 42, 'sigla' => 'SC', 'nome' => 'Santa Catarina'],
            ['id' => 35, 'sigla' => 'SP', 'nome' => 'São Paulo'],
            ['id' => 28, 'sigla' => 'SE', 'nome' => 'Sergipe'],
            ['id' => 17, 'sigla' => 'TO', 'nome' => 'Tocantins'],
        ];
    }
}
