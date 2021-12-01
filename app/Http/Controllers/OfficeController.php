<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Validators\OfficeValidator;
use App\Notifications\OfficePendingApproval;
use Illuminate\Auth\Middleware\Authorize;
// use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Nette\Utils\Json;
use Illuminate\Support\Facades\Storage;

class OfficeController extends Controller
{
    public function index(): JsonResource
    {
        $offices = Office::query()
            ->when(request('user_id') && auth()->guard('sanctum')->user() && request('user_id') == auth()->user()->id,
                fn($builder) => $builder,
                fn($builder) => $builder->where('approval_status', Office::APPROVAL_APPROVED)->where('hidden', false)
            )
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

        Notification::send(User::where('is_admin', true)->get(), new OfficePendingApproval($office));

        return OfficeResource::make(
            $office->load(['images', 'tags', 'user']),
        );
    }

    public function update(Office $office): JsonResource
    {
        if(!auth()->user()->tokenCan('office.update')){
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->authorize('update', $office);

        $data = (new OfficeValidator())->validate($office, request()->all());

        $office->fill(Arr::except($data, ['tags']));

        if($requiresReview = $office->isDirty(['lat', 'lng', 'price_per_day'])){
            $office->fill(['approval_status' => Office::APPROVAL_PENDING]);
        }

        DB::transaction(function () use ($office, $data){
            $office->save();
    
            if(isset($data['tags']))
            {
                $office->tags()->sync($data['tags']);
            }
        });

        if($requiresReview){
            Notification::send(User::where('is_admin', true)->get(), new OfficePendingApproval($office));
        }

        return OfficeResource::make(
            $office->load(['images', 'tags', 'user'])
        );
    }

    public function delete(Office $office)
    {
        if(!auth()->user()->tokenCan('office.delete')){
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->authorize('delete', $office);

        if($office->reservations()->where('status', Reservation::STATUS_ACTIVE)->exists()){
            throw ValidationException::withMessages(['office' => 'Cannot delete this office because it has active reservations.']);
        }

        $office->images()->each(function($image) {
            Storage::delete($image->path);
            $image->delete();
        });

        $office->delete();
    }
}
