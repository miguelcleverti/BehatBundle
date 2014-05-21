<?php
/**
 * File containing the Authentication sentences interface for BehatBundle.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace EzSystems\BehatBundle\Sentence;

interface Authentication
{
    /**
     * @Given /^I am not logged in$/
     */
    public function iAmNotLoggedIn();

    /**
     * @Given /^I am logged in as "(?P<user>[^"]*)" with password "(?P<password>[^"]*)"$/
     */
    public function iAmLoggedInAsWithPassword( $user, $password );

    /**
     * @Given /^I am logged (?:in |)as (?:an |a |)"(?P<role>[^"]*)"$/
     */
    public function iAmLoggedInAsAn( $role );
}
