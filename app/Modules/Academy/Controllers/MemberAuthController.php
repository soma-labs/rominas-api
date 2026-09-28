<?php

declare(strict_types=1);

namespace Rominas\Academy\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Member\Resources\MemberResource;
use Rominas\Academy\Requests\MemberMagicLinkRequestRequest;
use Rominas\Academy\Requests\MemberMagicLinkVerifyRequest;
use Rominas\Auth\MagicLink\Actions\VerifyMagicLinkAction;
use Rominas\Auth\MagicLink\Jobs\SendMagicLinkJob;

use function response;

/**
 * Member-facing passwordless auth on the `member` Sanctum guard. Login is a two-step magic link:
 * request a link by email, then verify the emailed token to receive a bearer token.
 */
class MemberAuthController
{
    /**
     * Request a magic link. Always 200 — the response never reveals whether the address has an
     * account (the token store is closed; a link is only actually sent to a known member).
     */
    public function requestLink(MemberMagicLinkRequestRequest $request): JsonResponse
    {
        SendMagicLinkJob::dispatch('member', $request->validated()['email'], 'academy-magic-link-email');

        return response()->json([
            'success' => true,
            'message' => __('If this address is registered, you will receive a sign-in link.'),
        ]);
    }

    /**
     * Confirm the magic link and issue the member's Sanctum token. First successful login activates
     * the account (marks the email verified). Suspended members are refused.
     */
    public function verify(MemberMagicLinkVerifyRequest $request, VerifyMagicLinkAction $verify): JsonResponse
    {
        $data = $request->validated();

        $member = $verify->execute('member', $data['email'], $data['token']);

        if (! $member instanceof Member || $member->status === MemberStatus::Suspended) {
            return response()->json(['message' => __('The sign-in link is invalid or has expired.')], 422);
        }

        $member->forceFill([
            'status' => MemberStatus::Active,
            'email_verified_at' => $member->email_verified_at ?? now(),
            'activated_at' => $member->activated_at ?? now(),
        ])->save();

        $token = $member->createToken($member->email, ['member']);

        return response()->json([
            'memberId' => $member->id,
            'token' => $token->plainTextToken,
        ]);
    }

    public function me(): MemberResource
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        return new MemberResource($member);
    }

    public function logout(): JsonResponse
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        $member->currentAccessToken()->delete();

        return response()->json(['success' => true]);
    }
}
