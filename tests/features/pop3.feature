@pop3
Feature: Authentication with a pop3 server

  Background:
    Given Connection to pop3 server

  @crammd5
  Scenario: Authenticate with pop3 server through CRAM-MD5 mechanism
    Given challenge received at auth request method "CRAM-MD5"
    When Autenticate with CRAM-MD5
    Then should be authenticate at pop3 server
