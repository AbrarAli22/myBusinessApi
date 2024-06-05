<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;

class GoogleController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function socialiteRedirect()
    {
        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/business.manage'])
            ->stateless()
            ->redirect();
    }

    /**
     * Handle the callback from Google authentication and log the user in.
     * @throws \Exception
     */
    public function handleGoogleCallback(Request $request)
    {
        // try {
        $user = Socialite::driver('google')->stateless()->user();
        $findUser = User::where('google_id', $user->id)->first();
        // dd($findUser);

        if ($findUser) {
            $findUser->update([
                'google_token' => $user->token,
                'google_refresh_token' => $user->refreshToken,
            ]);
            Auth::login($findUser);
        } else {
            $newUser = User::create([
                'name' => $user->name,
                'email' => $user->email,
                'image' => $user->avatar,
                'google_token' => $user->token,
                'google_refresh_token' => $user->refreshToken,
                'google_id' => $user->id,
            ]);
            Auth::login($newUser);
        }

        return redirect()->route('google.business.show');
        // } catch (\Exception $e) {
        //     dd($e->getMessage());
        // }
    }

    public function showBusinessData()
    {
        $user = Auth::user();

        if (!$user || !$user->google_token) {
            return redirect()->route('socialite.login')->with('error', 'Authentication required.');
        }

        $accessToken = $user->google_token;
        // dd($accessToken);

        // Fetch accounts
        $client = new Client();

        $response = $client->request('GET', 'https://mybusinessbusinessinformation.googleapis.com/$discovery/rest?version=v1', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ]);
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        // dd($data['resources']['accounts']);
        // $account = $data['accounts'];
        // dd($account);
        // Assuming "account" is a key in the JSON response
        if (isset($data['account'])) {
            $account = $data['account'];
            echo $account;
        } else {
            echo "Account not found in the response.";
        }
        exit;
        // $response = Http::withToken($accessToken)->get('https://mybusinessbusinessinformation.googleapis.com/$discovery/rest?version=v1');
        $accounts = $body->locations;

        if ($response->successful()) {
            dd($accounts);

            if (!empty($accounts)) {
                $accountName = $accounts[0]['name'];

                // Fetch locations
                $locationsResponse = Http::withToken($accessToken)->get("https://mybusinessbusinessinformation.googleapis.com/v1/{$accountName}/locations");

                if ($locationsResponse->successful()) {
                    $locations = $locationsResponse->json('locations');

                    foreach ($locations as $location) {
                        // Fetch reviews for each location
                        $reviewsResponse = Http::withToken($accessToken)->get("https://mybusinessbusinessinformation.googleapis.com/v1/{$location['name']}/reviews");

                        $reviews = [];
                        if ($reviewsResponse->successful()) {
                            $reviews = $reviewsResponse->json('reviews');
                        }

                        echo "Location Name: " . $location['locationName'] . "<br>";
                        echo "Address: " . implode(', ', $location['address']['addressLines']) . "<br>";
                        echo "Number of Reviews: " . count($reviews) . "<br>";
                        echo "Reviews: <br>";

                        foreach ($reviews as $review) {
                            echo "Reviewer: " . $review['reviewer']['displayName'] . "<br>";
                            echo "Comment: " . $review['comment'] . "<br>";
                            echo "Rating: " . $review['starRating'] . "<br><br>";
                        }
                    }
                } else {
                    return ('Failed to fetch locations: ' . $locationsResponse->json());
                }
            } else {
                return ('No accounts found: ' . $response->json());
            }
        } else {
            return ('Failed to fetch accounts: ' . $response->json());
        }

        return redirect()->route('socialite.login')->with('error', 'Failed to fetch Google My Business data.');
    }
}
