Feature: Authentication with a xmpp server

  Background:
    Given Connection to xmpp server
    And Connection to pop3 server

  Scenario: Authenticate with xmpp server through plain text authentication
    Given xmpp server supports authentication method "PLAIN"
    When authenticate with method PLAIN
    Then should be authenticated at xmpp server

  Scenario: Authenticate with xmpp server through digest-md5 authentication
    Given xmpp server supports authentication method "DIGEST-MD5"
    When authenticate with method DIGEST-MD5
    And responde to challenge received for DIGEST-MD5
    And responde to rspauth challenge
    Then should be authenticated at xmpp server

  Scenario: Authenticate with xmpp server through scram-sha-1 authentication
    Given xmpp server supports authentication method "SCRAM-SHA-1"
    When authenticate with method SCRAM-SHA-1
    And responde to challenge for SCRAM-SHA-1
    Then should be authenticated at xmpp server with verification

  Scenario: Authenticate with pop3 server through CRAM-MD5 mechanism
    Given challenge received at auth request method "CRAM-MD5"
    When Autenticate with CRAM-MD5
    Then should be authenticate at pop3 server
