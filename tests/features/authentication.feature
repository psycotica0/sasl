Feature: Authentication at a xmpp server

  Background:
    Given Connection to xmpp server

  Scenario: Authenticate to xmpp server through plain text authentication
    Given xmpp server supports authentication method "PLAIN"
    When authenticate with method PLAIN
    Then should be authenticated with server

  Scenario: Authenticate to xmpp server through plain text authentication
    Given xmpp server supports authentication method "DIGEST-MD5"
    When authenticate with method DIGEST-MD5
    And response to challenge received
    And response to rspauth challenge
    Then should be authenticated with server
