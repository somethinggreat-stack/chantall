<?php

namespace App\Http\Controllers;

use App\Models\ContactSubmission;
use App\Models\Enrollment;
use App\Models\FundingLead;
use App\Models\PopupSubmission;
use App\Models\TaxLead;
use Illuminate\Http\Request;

class IntakeController extends Controller
{
    /**
     * VIP welcome popup (index / tax / funding pages).
     */
    public function popup(Request $request)
    {
        $row = PopupSubmission::create([
            'name'      => $request->input('name'),
            'email'     => $request->input('email'),
            'phone'     => $request->input('phone'),
            'interests' => $request->input('interests'),
            'source'    => $request->input('source'),
            'page'      => $request->input('page', $request->headers->get('referer')),
        ]);

        return response()->json(['ok' => true, 'id' => $row->id]);
    }

    /**
     * Contact Us page form.
     */
    public function contact(Request $request)
    {
        $row = ContactSubmission::create([
            'name'    => $request->input('name'),
            'email'   => $request->input('email'),
            'phone'   => $request->input('phone'),
            'subject' => $request->input('subject'),
            'message' => $request->input('message'),
        ]);

        return response()->json(['ok' => true, 'id' => $row->id]);
    }

    /**
     * Tax page "Request Your Free Tax Review" form.
     */
    public function tax(Request $request)
    {
        $row = TaxLead::create([
            'name'    => $request->input('name'),
            'email'   => $request->input('email'),
            'phone'   => $request->input('phone'),
            'service' => $request->input('service'),
            'message' => $request->input('message'),
        ]);

        return response()->json(['ok' => true, 'id' => $row->id]);
    }

    /**
     * Funding eligibility quiz (9 steps).
     */
    public function funding(Request $request)
    {
        $a = (array) $request->input('answers', []);

        $row = FundingLead::create([
            'first_name'         => $a['fq_first'] ?? null,
            'last_name'          => $a['fq_last'] ?? null,
            'email'              => $a['fq_email'] ?? null,
            'phone'              => $a['fq_phone'] ?? null,
            'funding_goal'       => $a['Funding Goal'] ?? null,
            'confirmation'       => $a['Confirmation'] ?? null,
            'credit_cards'       => $a['Credit Cards'] ?? null,
            'credit_utilization' => $a['Credit Utilization'] ?? null,
            'credit_score'       => $a['Credit Score'] ?? null,
            'business_situation' => $a['Business Situation'] ?? null,
            'annual_income'      => $a['Annual Income'] ?? null,
            'credit_profile'     => $a['Credit Profile'] ?? null,
            'answers'            => $a,
        ]);

        return response()->json(['ok' => true, 'id' => $row->id]);
    }

    /**
     * Checkout enrollment (no card data is stored — no processor connected).
     */
    public function enroll(Request $request)
    {
        $row = Enrollment::create([
            'plan'            => $request->input('plan'),
            'amount'          => $request->input('amount'),
            'first_name'      => $request->input('first'),
            'last_name'       => $request->input('last'),
            'email'           => $request->input('email'),
            'phone'           => $request->input('phone'),
            'address'         => $request->input('address'),
            'city'            => $request->input('city'),
            'state'           => $request->input('state'),
            'zip'             => $request->input('zip'),
            'agree_terms'     => (bool) $request->input('agree_terms'),
            'agree_privacy'   => (bool) $request->input('agree_privacy'),
            'agree_marketing' => (bool) $request->input('agree_marketing'),
        ]);

        return response()->json(['ok' => true, 'id' => $row->id]);
    }
}
