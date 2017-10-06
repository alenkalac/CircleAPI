package model;

public class FetchResponse
{
    private String timestamp;

    private String is_order;

    private String id;

    private String complete;

    private String is_request;

    private String value;

    private String user_id;

    private String is_return;

    private String transaction_id;

    private String currency;

    public String getTimestamp ()
    {
        return timestamp;
    }

    public void setTimestamp (String timestamp)
    {
        this.timestamp = timestamp;
    }

    public String getIs_order ()
    {
        return is_order;
    }

    public void setIs_order (String is_order)
    {
        this.is_order = is_order;
    }

    public String getId ()
    {
        return id;
    }

    public void setId (String id)
    {
        this.id = id;
    }

    public String getComplete ()
    {
        return complete;
    }

    public void setComplete (String complete)
    {
        this.complete = complete;
    }

    public String getIs_request ()
    {
        return is_request;
    }

    public void setIs_request (String is_request)
    {
        this.is_request = is_request;
    }

    public String getValue ()
    {
        return value;
    }

    public void setValue (String value)
    {
        this.value = value;
    }

    public String getUser_id ()
    {
        return user_id;
    }

    public void setUser_id (String user_id)
    {
        this.user_id = user_id;
    }

    public String getIs_return ()
    {
        return is_return;
    }

    public void setIs_return (String is_return)
    {
        this.is_return = is_return;
    }

    public String getTransaction_id ()
    {
        return transaction_id;
    }

    public void setTransaction_id (String transaction_id)
    {
        this.transaction_id = transaction_id;
    }

    public String getCurrency ()
    {
        return currency;
    }

    public void setCurrency (String currency)
    {
        this.currency = currency;
    }

    @Override
    public String toString()
    {
        return "ClassPojo [timestamp = "+timestamp+", is_order = "+is_order+", id = "+id+", complete = "+complete+", is_request = "+is_request+", value = "+value+", user_id = "+user_id+", is_return = "+is_return+", transaction_id = "+transaction_id+", currency = "+currency+"]";
    }
}