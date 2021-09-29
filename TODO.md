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

[] Notify admin when new office

## Office photos

[] Attaching photos to an office
[] Allow choosing a photo to become the featured photo
[] Deleting a photo
    - Must have at least one photo if it's approved

## Update office endpoint

[x] host must be authenticated & email verified
[x] Token (if exits) must allow `office.update`
[] Can only update their own offices
[x] Validation
[] Mark as pending when critical attributes are updated and notify admin

## Delete office endpoint

[] host must be authenticated & email verified
[] Token (if exits) must allow `office.delete`
[] Can only delete their own offices
[] Cannot delete an office that has a reservation

## List reservations endpoint

[] 