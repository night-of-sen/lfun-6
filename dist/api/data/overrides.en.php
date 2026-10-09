<?php
/* English content overrides — fills the gaps left by the auto-generated
 * dist/api/data/content.en.php so the English library reaches parity with the
 * Chinese one. Merged by content_merge() in dist/api/lib/content.php.
 *
 * 'palaces'    keyed by palace order: Da An, Liu Lian, Su Xi, Chi Kou, Xiao Ji, Kong Wang.
 * 'categories' 'signal' plus 'subtypes'; each subtype 'pattern' is a PHP-safe PCRE
 *              fragment without delimiters, matched case-insensitively by
 *              content_subtype(). 'lost' is included for parity as well.
 * Tone: restrained, concrete, decision handed back to the user. No guarantees,
 * no fortune-telling promises; health defers to professional care, finance to
 * confirmed funds only, travel to official information. */
return [
    'palaces' => [
        ['nature' => 'The stable, protective palace: order, continuity and conditions that already hold.', 'process' => 'As the day palace it shows a steady process, working from agreed terms and existing arrangements.', 'focus' => 'Written terms, confirmed dates, your own record and the people already involved.', 'avoid' => 'Letting calm conditions postpone a confirmation that the plan still needs.'],
        ['nature' => 'The lingering palace: delay, repetition and details that return for another pass.', 'process' => 'As the day palace it shows a slow process with review loops, waiting and unfinished detail.', 'focus' => 'Outstanding replies, repeated revisions, unowned tasks and unanswered questions.', 'avoid' => 'Re-sending the same request or reading a delay as a final refusal.'],
        ['nature' => 'The swift palace: news, movement and a short window in which to act.', 'process' => 'As the day palace it shows quick replies, fast decisions and a narrow opening for action.', 'focus' => 'Messages, invitations, close deadlines and the next concrete opportunity to respond.', 'avoid' => 'Turning verbal enthusiasm straight into an irreversible commitment.'],
        ['nature' => 'The contested palace: friction in wording, boundaries and divided responsibility.', 'process' => 'As the day palace it shows a process tested by disagreement, unclear ownership and sharp phrasing.', 'focus' => 'Exact wording, cost, duties, authority and who is accountable for what.', 'avoid' => 'Replying while annoyed, or signing while the responsibility stays blurred.'],
        ['nature' => 'The cooperative palace: support, small gains and progress built on shared effort.', 'process' => 'As the day palace it shows gradual progress through referrals, partners and outside resources.', 'focus' => 'Reliable collaborators, introductions, complementary resources and the first small result.', 'avoid' => 'Reading an early, easy win as permission to scale up without checking the terms.'],
        ['nature' => 'The empty palace: missing information, open questions and unverified assumptions.', 'process' => 'As the day palace it shows a process carrying gaps, soft dates and evidence that has not arrived.', 'focus' => 'The facts still unavailable, the assumptions in use and the real size of the downside.', 'avoid' => 'Filling the gap with imagination or treating absence of news as a result.'],
    ],
    'categories' => [
        'work' => [
            'signal' => 'A written invitation, a confirmed next-round date or formal terms on paper.',
            'subtypes' => [
                ['pattern' => 'interview|second round|final round|screening', 'label' => 'Interview progress', 'guidance' => 'Prepare three concrete cases that prove the required skills, and confirm the next round in writing.'],
                ['pattern' => 'offer|contract|salary|compensation|negotiat', 'label' => 'Offer and terms', 'guidance' => 'Before accepting, check the title, salary, probation, reporting line and start date in writing.'],
                ['pattern' => 'resign|notice|leaving|quit|handover', 'label' => 'Resignation and handover', 'guidance' => 'Count the notice period, the handover duty and the savings buffer before you name a last day.'],
                ['pattern' => 'promotion|raise|review|appraisal', 'label' => 'Promotion and raise', 'guidance' => 'Open with documented results and changed responsibilities rather than with how you feel.'],
                ['pattern' => 'resume|cv|applying|application|job search|referral', 'label' => 'Application and referral', 'guidance' => 'Tailor the materials to the posting, and keep more than one process running at once.'],
            ],
        ],
        'cooperation' => [
            'signal' => 'A named owner, a confirmed budget and signed delivery terms.',
            'subtypes' => [
                ['pattern' => 'launch|release|go.?live|delivery|ship', 'label' => 'Launch and delivery', 'guidance' => 'Fix the acceptance criteria, the owner, the release window and the rollback plan before starting.'],
                ['pattern' => 'partner|partnership|joint venture|co-?found|alliance', 'label' => 'Partnership', 'guidance' => 'Confirm contributions, equity, decision rights and the exit route before committing.'],
                ['pattern' => 'contract|sign|agreement|terms|clause', 'label' => 'Contract terms', 'guidance' => 'Read the price, liability, change and termination clauses line by line before signing.'],
                ['pattern' => 'budget|quote|pricing|invoice|cost', 'label' => 'Budget and pricing', 'guidance' => 'Get the quote, the payment schedule and the change rate in writing before work starts.'],
                ['pattern' => 'timeline|deadline|milestone|schedule', 'label' => 'Timeline and milestones', 'guidance' => 'Give every milestone one owner and one date that both sides have confirmed.'],
            ],
        ],
        'relationship' => [
            'signal' => 'Contact stays consistent, and plans are actually kept rather than only promised.',
            'subtypes' => [
                ['pattern' => 'reconcile|getting back|ex\\b|second chance', 'label' => 'Reconnection', 'guidance' => 'Ask what would be different this time, and watch whether the answer becomes behavior.'],
                ['pattern' => 'confess|confession|crush|like someone|proposal', 'label' => 'Declaring feelings', 'guidance' => 'Say what you want plainly, and leave the other person a genuine, pressure-free way to answer.'],
                ['pattern' => 'breakup|break up|divorce|separat|end it', 'label' => 'Ending a relationship', 'guidance' => 'Decide while calm, put practical arrangements in writing, and put your own safety first.'],
                ['pattern' => 'argument|fight|conflict|cold war|silent treatment|misunderstand', 'label' => 'Conflict and repair', 'guidance' => 'State one fact, one feeling and one specific request instead of replaying the whole history.'],
                ['pattern' => 'marriage|engaged|moving in|long distance|family', 'label' => 'Commitment steps', 'guidance' => 'Compare expectations about money, time, family and location before making a joint decision.'],
            ],
        ],
        'finance' => [
            'signal' => 'Money is actually received, and the terms and exit conditions are documented.',
            'subtypes' => [
                ['pattern' => 'payment|invoice|receivable|outstanding|client owes|paid', 'label' => 'Payment collection', 'guidance' => 'Confirm the payer, the invoice number, the condition and the exact due date in writing.'],
                ['pattern' => 'invest|stock|fund|portfolio|trading|crypto', 'label' => 'Investment decisions', 'guidance' => 'Set the maximum loss first, and treat any projection as unconfirmed until it is realised.'],
                ['pattern' => 'loan|borrow|lend|debt|mortgage|credit', 'label' => 'Borrowing and lending', 'guidance' => 'Work out the total cost, the repayment schedule and the consequences of a missed payment first.'],
                ['pattern' => 'income|salary|bonus|raise|revenue|side income', 'label' => 'Income changes', 'guidance' => 'Separate money already received from money that has only been discussed.'],
                ['pattern' => 'refund|deposit|fee|charge|dispute|warranty', 'label' => 'Fees and disputes', 'guidance' => 'Keep the receipt, the terms and the correspondence, and ask for every disputed charge in writing.'],
            ],
        ],
        'study' => [
            'signal' => 'Mock scores, error rates or completed material move consistently in the right direction.',
            'subtypes' => [
                ['pattern' => 'exam|test|mock|paper|final|midterm', 'label' => 'Exam preparation', 'guidance' => 'Judge readiness from timed mock scores and the error distribution, not from how confident you feel.'],
                ['pattern' => 'application|admission|enrol|register|deadline|eligib', 'label' => 'Application and admission', 'guidance' => 'Check the official requirements, materials and deadline, and put the dates in your own calendar.'],
                ['pattern' => 'study|revision|coursework|assignment|homework|thesis', 'label' => 'Study routine', 'guidance' => 'Break the target into work you can finish and check on the same day.'],
                ['pattern' => 'teacher|tutor|mentor|classmate|study group|feedback', 'label' => 'Feedback and support', 'guidance' => 'Bring written questions to one session, and correct the weak part while it is fresh.'],
            ],
        ],
        'health' => [
            'signal' => 'Recorded symptoms, test results and a clinician\'s assessment show objective change.',
            'subtypes' => [
                ['pattern' => 'test|scan|report|result|indicator|checkup|blood', 'label' => 'Tests and results', 'guidance' => 'Have the result interpreted by a clinician together with your history, not in isolation.'],
                ['pattern' => 'surgery|operation|treatment|medication|medicine|therapy|dose', 'label' => 'Treatment and medication', 'guidance' => 'Follow the prescribed plan, and raise any change with your doctor instead of adjusting it yourself.'],
                ['pattern' => 'pain|symptom|ill|sick|fever|dizz|nausea|ache', 'label' => 'Symptoms', 'guidance' => 'Record severity, duration and triggers, and seek a professional assessment if the pattern persists or worsens.'],
                ['pattern' => 'pregnan|recovery|rehab|follow.?up|aftercare|chronic', 'label' => 'Recovery and follow-up', 'guidance' => 'Keep the follow-up appointments and let the clinical signs, not the reading, set the pace.'],
            ],
        ],
        'travel' => [
            'signal' => 'Official schedules, weather, documents and bookings all remain valid and confirmed.',
            'subtypes' => [
                ['pattern' => 'flight|plane|airport|train|rail|coach|bus|transit|connection', 'label' => 'Transport and transfers', 'guidance' => 'Rely on the official schedule and notices, and keep a rebooking option for the tightest connection.'],
                ['pattern' => 'visa|passport|entry|document|permit|customs', 'label' => 'Documents and entry', 'guidance' => 'Verify validity and entry rules on the official source, and keep copies accessible offline.'],
                ['pattern' => 'hotel|booking|reservation|airbnb|accommodation', 'label' => 'Bookings', 'guidance' => 'Reconfirm the reservation and the cancellation terms directly with the provider.'],
                ['pattern' => 'business trip|itinerary|departure|return|moving|relocat', 'label' => 'Trip planning', 'guidance' => 'Check documents, weather, cost and the return leg before you pay anything.'],
                ['pattern' => 'weather|storm|typhoon|warning|delay|cancel', 'label' => 'Weather and disruption', 'guidance' => 'Follow the official warning and the operator\'s notice, and change the plan rather than pressing on.'],
            ],
        ],
        'general' => [
            'signal' => 'A key condition is supported by evidence, and the worst case is affordable.',
            'subtypes' => [
                ['pattern' => 'buy|purchase|choose|decision|should i|or not', 'label' => 'Everyday choices', 'guidance' => 'List the unacceptable cost, pick the reversible option and set a date to review it.'],
                ['pattern' => 'move|relocat|house|job change|career change|major', 'label' => 'Major life changes', 'guidance' => 'Test the change on a small scale, and keep one reversible route open.'],
                ['pattern' => 'family|parents|children|relatives|friend', 'label' => 'Family and friends', 'guidance' => 'Separate what was actually said from what you assume was meant, then ask one clear question.'],
                ['pattern' => 'deadline|decision date|timing|when', 'label' => 'Timing a decision', 'guidance' => 'Fix a decision date, and treat the missing evidence as a reason to wait, not to guess.'],
            ],
        ],
        'lost' => [
            'label' => 'Lost item',
            'signal' => 'A verified sighting, a tracking record or a lost-property match on an official channel.',
            'subtypes' => [
                ['pattern' => 'lost|missing|cannot find|can\'t find|misplaced', 'label' => 'Lost item', 'guidance' => 'Search one zone completely from the last confirmed place before repeating it.'],
                ['pattern' => 'stolen|theft|robbed|burglar|pickpocket', 'label' => 'Theft', 'guidance' => 'Report it, block or lock the accounts and devices, and keep the reference numbers.'],
                ['pattern' => 'phone|wallet|keys|ring|passport|laptop|document', 'label' => 'Valuable items', 'guidance' => 'Use device tracking, account protection and the lost-property service alongside the search.'],
            ],
        ],
    ],

    /* Lost item: fills base.timing (empty in the generated library) and supplies the
     * search order used by the paid reading. Direction, place and chance already come
     * from content.en.php. */
    'lost' => [
        'base' => [
            ['timing' => 'Search immediately along the route you last used.'],
            ['timing' => 'Empty each zone completely before moving on; a quick glance will miss it.'],
            ['timing' => 'Act quickly and ask companions or staff while the trail is fresh.'],
            ['timing' => 'Ask on-site staff now, and check cameras or lost property immediately.'],
            ['timing' => 'Ask family, colleagues or whoever shares the space to help.'],
            ['timing' => 'Do not rely on direction alone; start with lost property, account and device protection.'],
        ],
        'actions' => [
            'en' => [
                'Start from the last place you are certain you saw it and retrace the route in reverse.',
                'Search one zone completely before moving to the next; do not just glance.',
                'Contact companions, on-site staff and lost-property services; protect valuables immediately.',
            ],
        ],
    ],
];
