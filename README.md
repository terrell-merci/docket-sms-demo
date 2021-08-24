## Docket-SMS 
##### (PHP based algorithm behind the Docket TMSL application)
Last year, I started working with a rising transportation company to see if we could develop a light-weight solution for a niche problem. I was tasked with the development of an application that was capable of automating communication between 3rd party truck drivers and the dispatchers in which this company employed. Over multiple iterations, this package is what resulted as the primary backend structure for the Docket TMSL application.

###### *Please keep in mind that this repository only displays demo code. Most files unfortunately have to be omiited as it is proprietary. 

### Features
* **Automated communication:** Serves as an intermediary between multiple parties, automating communication between them via SMS and email.
* **State based infrastructure:** 'States' are the building blocks behind the logic of Docket SMS. Each state is a class with a series of methods and properties. Docket is able to determine the state of a "load" by each SMS message, 24 hour period, appointment, or even entire trip.
* **Cleverly reads text:** Uses string parsing as well as the events of the trip up to that point to determine the intent (or state) of a returned SMS message and notify all parties accordingly.

### Usage
This package was developed to be easy to use throughout the application. For example, to send or receive a text via an http request is as simple as:
```php
Docket::SMS($request)->send();
// or
Docket::SMS($request)->receive();
```
In this instance, needed properties such as `string $message`, `string $from`, and `string $to` would be passed via the `$request` object. Docket determines the `object $state` based on the context of the message itself, as well as the events of the trip up to that point. It will then send the correct message on a send call, or stores the message and responds to driver accordingly on a receive call. 

But, all of these properties can be provided directly as well:
```php
Docket::SMS()->message('Hello driver!')->to('16172224444')->from('Docket')->send();
```
Providing the state name will negate Docket having to determine the `object $state` property itself. 
```php
Docket::SMS()->state('Sent')->send();
```
If needed, a developer can receive a list of states by calling `stateOptions()`. This will return an array of applicable states based on given context.
```php
Docket::SMS()->stateOptions();

// returns something like:
array:7 [
  0 => "Confirmed"
  1 => "Reschedule"
  2 => "Unconfirmed"
  3 => "Inquiry"
  4 => "WrongDriver"
  5 => "Misc"
  6 => "Update"
]
```
