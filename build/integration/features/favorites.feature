Feature: favorite
    Background:
        Given using api version "1"

    Scenario: Favorite a folder
        Given using old dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/FOLDER"
        Then as "user0" gets properties of folder "/FOLDER" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value "1"

    Scenario: Favorite and unfavorite a folder
        Given using old dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/FOLDER"
        And user "user0" unfavorites element "/FOLDER"
        Then as "user0" gets properties of folder "/FOLDER" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value ""

    Scenario: Favorite a file
        Given using old dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/textfile0.txt"
        Then as "user0" gets properties of file "/textfile0.txt" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value "1"

    Scenario: Favorite and unfavorite a file
        Given using old dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/textfile0.txt"
        And user "user0" unfavorites element "/textfile0.txt"
        Then as "user0" gets properties of file "/textfile0.txt" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value ""

    Scenario: Favorite a folder new endpoint
        Given using new dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/FOLDER"
        Then as "user0" gets properties of folder "/FOLDER" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value "1"

    Scenario: Favorite and unfavorite a folder new endpoint
        Given using new dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/FOLDER"
        And user "user0" unfavorites element "/FOLDER"
        Then as "user0" gets properties of folder "/FOLDER" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value ""

    Scenario: Favorite a file new endpoint
        Given using new dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/textfile0.txt"
        Then as "user0" gets properties of file "/textfile0.txt" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value "1"

    Scenario: Favorite and unfavorite a file new endpoint
        Given using new dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/textfile0.txt"
        And user "user0" unfavorites element "/textfile0.txt"
        Then as "user0" gets properties of file "/textfile0.txt" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value ""

