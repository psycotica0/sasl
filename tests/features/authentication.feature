Feature: Plain text authentication method

  Background:
    Given Connection to xmpp server

  Scenario: Authenticate to xmpp server through plain text authentication
    Given xmpp server supports authentication method "PLAIN"
    When authenticate with method "PLAIN"
    Then should be authenticated with server

  Scenario: Authenticate to xmpp server through plain text authentication
    Given xmpp server supports authentication method "CRAM-MD5"
    When authenticate with method "CRAM-MD5"
    Then should be authenticated with server
