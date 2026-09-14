<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPhone;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Customer::select([
                'id', 'name', 'slogan', 'description', 'title_address', 'patent_address',
                'address', 'number', 'complement', 'neighborhood', 'zipcode', 'city', 'state',
                'hide_address', 'site', 'email', 'category_id', 'city_id', 'url', 'status',
            ])
                ->with(['category:id,name,url,department_id', 'category.department:id,name,url', 'phones'])
                ->where('status', '1')
                ->orderBy('id', 'desc');

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('city_id')) {
                $query->where('city_id', $request->city_id);
            }

            if ($request->has('state')) {
                $query->where('state', strtoupper($request->state));
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            $perPage = min($request->get('per_page', 20), 50);
            $page = $request->get('page', 1);

            $customers = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'data' => $customers->map(fn (Customer $customer) => $this->formatCustomer($customer)),
                'meta' => [
                    'current_page' => $customers->currentPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'last_page' => $customers->lastPage(),
                    'from' => $customers->firstItem(),
                    'to' => $customers->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'city_id' => 'required|exists:cities,id',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'number' => 'nullable|integer',
            'complement' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'site' => 'nullable|string',
            'phone' => 'nullable|string',
        ]);

        $customer = Customer::create($validated);

        return response()->json($customer, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $customer = Customer::with(['category.department', 'phones'])
                ->where('status', '1')
                ->findOrFail($id);

            return response()->json($this->formatCustomer($customer, detailed: true));
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'city_id' => 'sometimes|exists:cities,id',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'number' => 'nullable|integer',
            'complement' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'site' => 'nullable|string',
            'status' => 'sometimes|string|max:1',
        ]);

        $customer->update($validated);

        return response()->json($customer);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->json(null, 204);
    }

    private function formatCustomer(Customer $customer, bool $detailed = false): array
    {
        $fields = [
            'id', 'name', 'slogan', 'description', 'title_address', 'patent_address',
            'address', 'number', 'complement', 'neighborhood', 'zipcode', 'city', 'state',
            'hide_address', 'site', 'email', 'category_id', 'city_id', 'url', 'status',
        ];

        $data = $customer->only($fields);

        $data['category_name'] = $customer->category?->name;
        $data['category_url'] = $customer->category?->url;
        $data['department_name'] = $customer->category?->department?->name;
        $data['department_url'] = $customer->category?->department?->url;

        // Endereço formatado respeitando hide_address
        $data['formatted_address'] = $this->formatAddress($customer);

        // Telefones (sempre formatados e disponíveis tanto na listagem quanto no detalhe)
        $phones = $customer->phones
            ? $customer->phones
                ->filter(fn (CustomerPhone $phone) => trim((string) $phone->phone) !== '' && trim((string) $phone->phone) !== '0')
                ->map(fn (CustomerPhone $phone) => $this->formatPhone($phone))
                ->values()
            : collect();

        $firstPhone = $phones->first();
        $whatsappPhone = $phones->firstWhere('type', 2);

        $data['phone'] = $firstPhone ? $firstPhone['display'] : null;
        $data['phone_tel'] = $firstPhone ? $firstPhone['tel'] : null;
        $data['whatsapp'] = $whatsappPhone ? $whatsappPhone['display'] : null;
        $data['whatsapp_url'] = $whatsappPhone ? $whatsappPhone['whatsapp_url'] : null;
        $data['phones'] = $phones->all();

        return $data;
    }

    private function formatAddress(Customer $customer): ?string
    {
        if ($customer->hide_address === 'S' || $customer->hide_address === 'Y') {
            return trim("{$customer->neighborhood} — {$customer->city}/{$customer->state}");
        }

        $parts = [];
        $title = trim((string) $customer->title_address);
        $patent = trim((string) $customer->patent_address);
        $address = trim((string) $customer->address);

        $streetPrefix = '';
        if ($title !== '') {
            $streetPrefix .= $title . '. ';
        }
        if ($patent !== '') {
            $streetPrefix .= $patent . ' ';
        }

        $street = trim($streetPrefix . $address);
        if ($street !== '') {
            $number = (int) $customer->number;
            $parts[] = $street . ($number > 0 ? ', ' . $number : '');
        }

        $complement = trim((string) $customer->complement);
        if ($complement !== '') {
            $parts[] = $complement;
        }

        $neighborhood = trim((string) $customer->neighborhood);
        if ($neighborhood !== '') {
            $parts[] = $neighborhood;
        }

        $parts[] = "{$customer->city}/{$customer->state}";

        $zipcode = trim((string) $customer->zipcode);
        if ($zipcode !== '') {
            $parts[] = "CEP {$zipcode}";
        }

        return implode(' - ', $parts);
    }

    private function formatPhone(CustomerPhone $phone): array
    {
        $digits = preg_replace('/\D/', '', (string) $phone->phone);
        $formatted = (string) $phone->phone;

        if (strlen($digits) === 8) {
            $formatted = substr($digits, 0, 4).'-'.substr($digits, 4);
        } elseif (strlen($digits) === 9) {
            $formatted = substr($digits, 0, 5).'-'.substr($digits, 5);
        } elseif (strlen($digits) === 11) {
            $formatted = substr($digits, 0, 4).' '.substr($digits, 4, 3).' '.substr($digits, 7);
        }

        $ddd = trim((string) $phone->ddd);
        $type = (int) $phone->type;

        if ($type === 3) {
            $tel = $formatted;
            $display = $formatted;
        } else {
            $tel = $ddd.$digits;
            $display = $ddd !== '' ? '(0'.$ddd.') '.$formatted : $formatted;
        }

        return [
            'type' => $type,
            'ddd' => $ddd,
            'phone' => $formatted,
            'display' => $display,
            'tel' => $tel,
            'whatsapp_url' => $type === 2 && $ddd !== ''
                ? 'https://wa.me/55'.$ddd.$digits.'?text=Quero%20saber%20mais'
                : null,
        ];
    }
}
