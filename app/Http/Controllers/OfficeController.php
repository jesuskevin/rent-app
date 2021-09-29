<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Validators\OfficeValidator;
use Illuminate\Auth\Middleware\Authorize;
// use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Nette\Utils\Json;

class OfficeController extends Controller
{
    public function index(): JsonResource
    {
        $offices = Office::query()
            ->where('approval_status', Office::APPROVAL_APPROVED)
            ->where('hidden', false)
            ->when(request('user_id'), fn (Builder $builder) => $builder->whereUserId(request('user_id')))
            ->when(request('visitor_id'), fn (Builder $builder) => $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id')))
            ->when(
                request('lat') && request('lng'), 
                fn (Builder $builder) => $builder->nearestTo(request('lat'), request('lng')),
                fn (Builder $builder) => $builder->orderBy('id', 'ASC')
            )
            ->with(['images', 'tags', 'user'])
            ->withCount(['reservations' => fn (Builder $builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->paginate(20);

        return OfficeResource::collection(
            $offices
        );
    }

    public function show(Office $office)
    {
        $office->loadCount(['reservations' => fn (Builder $builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->load(['images', 'tags', 'user']);

        return OfficeResource::make($office);
    }

    public function create(): JsonResource
    {
        if(!auth()->user()->tokenCan('office.create')){
            abort(Response::HTTP_FORBIDDEN);
        }

        $data = (new OfficeValidator())->validate(
            $office = new Office(),
            request()->all()
        );

        $data['approval_status'] = Office::APPROVAL_PENDING;
        $data['user_id'] = auth()->id();

        $office = DB::transaction(function () use ($office, $data){
            $office->fill(
                Arr::except($data, ['tags'])
            )->save();
    
            if(isset($data['tags']))
            {
                $office->tags()->attach($data['tags']);
            }

            return $office;
        });

        return OfficeResource::make($office);
    }

    public function update(Office $office): JsonResource
    {
        if(!auth()->user()->tokenCan('office.update')){
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->authorize('update', $office);

        $data = (new OfficeValidator())->validate($office, request()->all());

        DB::transaction(function () use ($office, $data){
            $office->update(
                Arr::except($data, ['tags'])
            );
    
            if(isset($data['tags']))
            {
                $office->tags()->sync($data['tags']);
            }
        });

        return OfficeResource::make($office);
    }
}
