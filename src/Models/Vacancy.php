<?php

namespace Timegridio\Concierge\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * @property Timegridio\Concierge\Models\Business $business
 * @property string $date Local timezone date of the published Vacancy
 */
class Vacancy extends EloquentModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'business_id',
        'service_id',
        'humanresource_id',
        'date',
        'start_at',
        'finish_at',
        'capacity',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['start_at', 'finish_at'];

    ///////////////////
    // Relationships //
    ///////////////////

    /**
     * belongs to Business.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo Relationship Vacancy belongs to Business query
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * for Service.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo Relationship Vacancy is for providing Service query
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Humanresource.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function humanresource()
    {
        return $this->belongsTo(Humanresource::class);
    }

    /**
     * holds many Appointments.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany Relationship Vacancy belongs to Business query
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    ////////////
    // Scopes //
    ////////////

    /**
     * Scope For Date.
     *
     * @param Illuminate\Database\Query $query
     * @param Carbon                    $date  Date of inquiry
     *
     * @return Illuminate\Database\Query Scoped query
     */
    public function scopeForDate($query, Carbon $date)
    {
        return $query->where('date', '=', $date->toDateString());
    }

    /**
     * Scope For DateTime.
     *
     * @param Illuminate\Database\Query $query
     * @param Carbon                    $datetime Date and Time of inquiry
     *
     * @return Illuminate\Database\Query Scoped query
     */
    public function scopeForDateTime($query, Carbon $datetime)
    {
        return $query->where('start_at', '<=', $datetime->toDateTimeString())
                        ->where('finish_at', '>=', $datetime->toDateTimeString());
    }

    /**
     * Scope only Future.
     *
     * @param Illuminate\Database\Query $query
     *
     * @return Illuminate\Database\Query Scoped query
     */
    public function scopeFuture($query)
    {
        return $query->where('date', '>', Carbon::now());
    }

    /**
     * Scope For Service.
     *
     * @param Illuminate\Database\Query $query
     * @param int serviceId  $service Inquired Service to filter
     *
     * @return Illuminate\Database\Query Scoped query
     */
    public function scopeForService($query, $serviceId)
    {
        return $query->where('service_id', '=', $serviceId);
    }

    /////////////////////
    // Soft Attributes //
    /////////////////////

    /**
     * is Holding Any Appointment for given User.
     *
     * ToDo: Remove from here as needs knowledge from User
     *
     * @param int $userId User to check belonging Appointments
     *
     * @return bool Vacancy holds at least one Appointment of User
     */
    public function isHoldingAnyFor($userId)
    {
        $appointments = $this->appointments()->get();

        foreach ($appointments as $appointment) {
            $contact = $appointment->contact()->first();
            if ($contact->isProfileOf($userId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * is Full.
     *
     * @return bool Vacancy is fully booked
     */
#    public function isFull()
#    {
#        return $this->getFreeSlotsCount() <= 0;
#    }

    /**
     * get free slots count.
     *
     * @return int Count Capacity minus Used
     */
#    public function getFreeSlotsCount()
#    {
#        $count = $this->appointments()->active()->count();
#
#        return $this->capacity - $count;
#    }

    /**
     * get capacity.
     *
     * @return int Capacity of the vacancy (in appointment instances)
     */
    public function getCapacityAttribute()
    {
        if ($this->humanresource) {
            return intval($this->humanresource->capacity);
        }

        return intval($this->attributes['capacity']);
    }

    /**
     * has Room.
     *
     * @return bool There is more capacity than used
     */
    public function hasRoom()
    {
        return $this->capacity > $this->appointments()->active()->count();
    }

    /**
     * has Room between time.
     *
     * @return bool There is more capacity than used
     */
    public function hasRoomBetween(Carbon $startAt, Carbon $finishAt)
    {
        return $this->capacity > $this->business
                                      ->bookings()
                                      ->active()
                                      ->affectingInterval($startAt, $finishAt)
                                      ->affectingHumanresource($this->humanresource_id)
                                      ->count() &&
            ($this->start_at <= $startAt && $this->finish_at >= $finishAt);
    }

    /**
     * Get available capacity between time.
     *
     * @return int Available capacity
     */
    public function getAvailableCapacityBetween(Carbon $startAt, Carbon $finishAt)
    {
        if (!($this->start_at <= $startAt && $this->finish_at >= $finishAt)) {
            return 0;
        }

        $count = $this->business
                      ->bookings()
                      ->with('humanresource')
                      ->active()
                      ->affectingHumanresource($this->humanresource_id)
                      ->affectingInterval($startAt, $finishAt)
                      ->count();

        return $this->capacity - intval($count);
    }
}
