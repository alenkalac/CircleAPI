package model;

public class LoginResponse {
    String error;
    String auth_token;

    public String getError() {
        return error;
    }

    public void setError(String error) {
        this.error = error;
    }

    public String getAuth_token() {
        return auth_token;
    }

    public void setAuth_token(String auth_token) {
        this.auth_token = auth_token;
    }

    @Override
    public String toString() {
        return "ClassPojo { error: " + this.getError() + ", auth_token" +  this.getAuth_token() + "}";
    }
}
