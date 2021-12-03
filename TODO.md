# TODO

<!-- [x] Prepare migrations
[x] Seed the inital tags
[x] Prepare models
[x] Prepare factories
[x] Prepare resources
[x] Tags
    - Routes
    - Controller
    - Tests
[x] Offices
    - List offices
    - Read offices
    - Create offices -->

## List offices endpoint

[x] show only approved and visible records
[x] filter by host
[x] filter by user
[x] includes tags, images and users
[x] show count of previous reservations
[x] paginate
[x] sort by distance if lat/lng is provided. Otherwise older first.

## Show office endpoint

[x] show count of previous reservations
[x] include tags, images and user

## List Offices Endpoint

[x] Change the user_id filter to visitor_id and host_id to user_id
[x] Switch to using custom Polymorphic types
[x] Order by distance but do not include the distance attribute
[x] Configure the resources

## Create office endpoint

[x] host must be authenticated & email verified
[x] Token (if exits) must allow `office.create`
[x] Validation

[x] Office approval status should be pending or approved only ... no rejected\
[x] Store office inside a database transaction

[x] Notify admin when new office

## Office photos

[x] Attaching photos to an office
[x] Allow choosing a photo to become the featured photo
[x] Deleting a photo
    - Must have at least one photo if it's approved

## Update office endpoint

[x] host must be authenticated & email verified
[x] Token (if exits) must allow `office.update`
[x] Can only update their own offices
[x] Validation
[x] Mark as pending when critical attributes are updated and notify admin

## Delete office endpoint

[x] host must be authenticated & email verified
[x] Token (if exits) must allow `office.delete`
[x] Can only delete their own offices
[x] Cannot delete an office that has a reservation

## 12/10/21

[x] Delete all the images when deleting an office
[x] Use the default disk to store public images so it's easier to switch to diferent drivers in production
[x] Use the keyed implicit binding in the office image routes so laravel scopes to the office that the image belongs to.
    - [Tweet](https://twitter.com/themsaid/status/1441323002222637062)

## List reservations endpoint

[x] Must be authenticated & email verified
[x] Token (if exits) must allow `reservations.show`
[x] Can only list their own reservations or reservations on their offices
[x] Allow filtering by office_id only for authenticated host
[x] Allow filtering by user_id only for authenticated user
[x] Allow filtering by date range
[x] Allow filtering by status
[x] Paginate

## 29/1/2021

[x] Switch to using Sanctum guard by default
[x] Use the new [assertNotDeleted](https://github.com/laravel/framework/pull/38886)
[x] Use the new LazilyRefreshDatabase testing trait on the base test class

## Make Reservations Endpoint

[x] Must be authenticated & email verified
[x] Token (if exits) must allow `reservations.make`
[x] Cannot make reservations on their own property
[x] Validate no other reservation conflicts with the same time
[x] Use locks to make the process atomic
[x] Read the request input form the validator output
[x] You cannot make a reservation on a pending or hidden office
[x] Test you can make a reservation starting next day but cannot make one on same day
[x] Email user & host when a reservation is made
[x] Email user & host on reservation start day
[x] Generate WIFI password for new reservations (store encrypted)

## Cancel Reservations endpoint

[x] Must be authenticated & email verified
[x] Token (if exits) must allow `reservations.cancel`
[x] Can only cancel their own reservations
[x] Can only cancel an active reservation that has a start_date in the future

## Housekeeping

[x] Convert filtering reservation by date to Eloquent Scopes
[x] Include reservations that started before range and ended after range while filtering
[x] Filter offices by tag
[x] API should return the full URI of the image so that the consumer can load easily
[] Test SendDueReservationsNotifications command