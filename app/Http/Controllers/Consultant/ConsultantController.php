<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Traits\Response;

class ConsultantController extends Controller
{
    use Response;

    protected $validationRules = [
        'consultants'   => 'array|required',
        'from'          => 'date|required',
        'to'            => 'date|required',
    ];

    public function index () {
        try {
            $consultants = DB::table('cao_usuario as cu')
                ->select(
                    'cu.uf_orgao_emissor as organization',
	                'cu.co_usuario as userName',
	                'cu.no_usuario as fullName',
	                'ps.co_tipo_usuario as typeUser',
	                'ps.co_sistema as system',
	                'ps.in_ativo as isActive'
                )
                ->join('permissao_sistema as ps', 'ps.co_usuario', 'cu.co_usuario')
                ->where('ps.co_sistema', 1)
                ->where('ps.in_ativo', 'S')
                ->whereIn('ps.co_tipo_usuario', [0, 1, 2])
                ->where('cu.uf_orgao_emissor', '<>', 'AC') // validar con el equipo
                ->orderBy('cu.no_usuario')
                ->get();

        } catch (\Throwable $th) {
            $this->reportError($th);
            return $this->error('Internal server error', $th, 500);
        }

        return $this->success(count($consultants) ? 'Success' : 'Not results', $consultants ?? []);
    }

    public function getReport(Request $request) {
        $validator = $this->validator($request->all(), $this->validationRules, class_basename($this));

        if ($validator->fails()) {
            return $this->error('Fields do not comply', $validator->errors(), 400);
        }

        $reports        = [];
        $consultants    = $request->consultants;
        $from           = $request->from;
        $to             = $request->to;

        try {
            $invoices = DB::table('cao_os as co')
                ->selectRaw('
                    co.co_usuario as username,
                    extract(year_month from cf.data_emissao) as date,
                    round(cs.brut_salario, 2) as salary,
                    sum(
                        round(cf.valor - (cf.valor * cf.total_imp_inc / 100), 2)
                    ) as net_income,
                    sum(
                        round((cf.valor - (cf.valor * cf.total_imp_inc / 100)) * cf.comissao_cn / 100, 2)
                    ) as commission,
                    round(
                        sum((cf.valor - (cf.valor * cf.total_imp_inc / 100)) - ((cf.valor - (cf.valor * cf.total_imp_inc / 100)) * cf.comissao_cn / 100)) - (cs.brut_salario), 2
                    ) as profit
                ')
                ->join('cao_fatura as cf', 'cf.co_os', 'co.co_os')
                ->join('cao_salario as cs', 'cs.co_usuario', 'co.co_usuario')
                ->whereIn('co.co_usuario', $consultants)
                ->whereBetween('cf.data_emissao', [$from, $to])
                ->whereColumn('co.co_sistema', 'cf.co_sistema')
                ->groupByRaw('1, 2, cs.brut_salario')
                ->orderBy('co.co_usuario')
                ->get();

            if (count($invoices)) {
                $reports = $invoices->groupBy('username');
            }
        } catch (\Throwable $th) {
            $this->reportError($th);
            return $this->error('Internal server error', $th, 500);
        }

        return $this->success(count($reports) ? 'Success' : 'Not results', $reports ?? []);
    }

    private function getDataForBarChart ($request) {
        $consultants    = $request->consultants;
        $from           = $request->from;
        $to             = $request->to;

        $invoicesTotal = DB::table('cao_os as co')
            ->selectRaw('
                co.co_usuario as username,
                month(cf.data_emissao) as date,
                cu.no_usuario as fullname,
                sum(
                    round(cf.valor - (cf.valor * cf.total_imp_inc / 100), 2)
                ) as net_income
            ')
            ->join('cao_fatura as cf', 'cf.co_os', 'co.co_os')
            ->join('cao_usuario as cu', 'cu.co_usuario', 'co.co_usuario')
            ->whereIn('co.co_usuario', $consultants)
            ->whereBetween('cf.data_emissao', [$from, $to])
            ->whereColumn('co.co_sistema', 'cf.co_sistema')
            ->groupByRaw('1, 2')
            ->orderBy('co.co_usuario')
            ->get();

        $average = DB::table('cao_salario as cs')
            ->selectRaw('round(sum(cs.brut_salario) / count(cs.brut_salario) , 2) as average_fixed_salary')
            ->whereIn('cs.co_usuario', $consultants)
            ->first();

        $consultants = DB::table('cao_usuario as cu')
            ->select(
                'cu.no_usuario as fullname',
                'cu.co_usuario as username'
            )
            ->whereIn('cu.co_usuario', $consultants)
            ->get();

        $fromToDate = explode('-', $from)[0] . ' a ' . explode('-', $to)[0];

        if ($invoicesTotal) {
            $detail = [
                'list'                  => $invoicesTotal->groupBy('username'),
                'max_income'            => $invoicesTotal->max('net_income'),
                'average_fixed_salary'  => isset($average->average_fixed_salary) ? $average->average_fixed_salary : 0,
                'consultants'           => $consultants,
                'from_to_date'          => $fromToDate
            ];
            return $detail;
        }

        return [];
    }

    private function getDataForPieChart ($request) {
        $consultants    = $request->consultants;
        $from           = $request->from;
        $to             = $request->to;

        $invoicesTotal = DB::table('cao_os as co')
            ->selectRaw('
                co.co_usuario as username,
                cu.no_usuario as fullname,
                sum(
                    round(cf.valor - (cf.valor * cf.total_imp_inc / 100), 2)
                ) as net_income
            ')
            ->join('cao_fatura as cf', 'cf.co_os', 'co.co_os')
            ->join('cao_usuario as cu', 'cu.co_usuario', 'co.co_usuario')
            ->whereIn('co.co_usuario', $consultants)
            ->whereBetween('cf.data_emissao', [$from, $to])
            ->whereColumn('co.co_sistema', 'cf.co_sistema')
            ->groupByRaw('1, 2')
            ->orderBy('co.co_usuario')
            ->get();

        if ($invoicesTotal) {
            $detail = [
                'list'  => $invoicesTotal,
                'total' => $invoicesTotal->sum('net_income')
            ];
            return $detail;
        }

        return [];
    }

    public function getDataGraph (Request $request, $type) {
        $validator = $this->validator($request->all(), $this->validationRules, class_basename($this));

        if ($validator->fails()) {
            return $this->error('Fields do not comply', $validator->errors(), 400);
        }

        $data = [];

        try {
            if ($type && ($type == 'bar' || $type == 'pie')) {
                if ($type == 'bar') {
                    $data = $this->getDataForBarChart($request);
                } else if ($type == 'pie') {
                    $data = $this->getDataForPieChart($request);
                }
            } else {
                return $this->error('Fields do not comply', [
                    'detail' => 'you must send a valid chart type (bar or pie)'
                ], 400);
            }
        } catch (\Throwable $th) {
            $this->reportError($th);
            return $this->error('Internal server error', $th, 500);
        }

        return $this->success(!empty($data) ? 'Success' : 'Not results', $data ?? []);
    }
}
