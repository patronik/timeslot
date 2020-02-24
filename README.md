# timeslot

## Intro
This is simple parser of time slot rules and time slot generator. What is time slot? It's just a time of an event or action which are happening according to schedule.

## Why do we need this?
This can be useful in different situations. For example: you doctor is accepting patients on Mondays from 12:00 to 14:00 and Fridays at 11:00 and at 13:30. Doctor dedicate 30 minutes for 1 patient. How many patients can visit this doctor in one week? This script can solve this problem easily.

## Usage
Let's assume that today's date is 2020-03-02 which is Monday. Let's create rule that generate all timeslots available on this week.

```
$parser = new Time_Slot_Generator();
$parser->parse(
    <<<RULE
EVERY 30 MINUTES 
FROM 2020-03-02
MON WITHIN 12:00-14:00
FRI AT 11:00 AND AT 13:30
TO 2020-03-08
RULE
);

echo '<pre>';
print_r($parser->generateTimeSlots());
echo '</pre>';
```

Output:
```
Array
(
    [0] => 2020-03-02 12:00:00
    [1] => 2020-03-02 12:30:00
    [2] => 2020-03-02 13:00:00
    [3] => 2020-03-02 13:30:00
    [4] => 2020-03-02 14:00:00
    [5] => 2020-03-06 11:00:00
    [6] => 2020-03-06 13:30:00
)
```

As you can see, doctor can only make 7 appointments this week. 

## Conclusions
Using this script we can generate time slots for different recurring activities and then send them to you customers so they will be able to choose desired time.
