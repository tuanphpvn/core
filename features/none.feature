Feature: Authorization checking
  In order to use the API
  As a client software developer
  I need to be authorized to access a given resource.

  Scenario: A standard user cannot create a secured resource
    When I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Authorization" header equal to "Basic ZHVuZ2xhczprZXZpbg=="
    And I send a "POST" request to "/secured_dummies" with body:
    """
    {
        "title": "Title",
        "description": "Description",
        "owner": "foo"
    }
    """
    Then the response status code should be 403
