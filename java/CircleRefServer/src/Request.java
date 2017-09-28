public class Request extends Thread {

    private String transactionID;
    private String token;
    private String owner;
    private boolean isComplete;

    public Request(String transactionID, String token, String owner) {
        this.transactionID = transactionID;
        this.token = token;
        this.owner = owner;
        this.isComplete = false;
    }

    public String getTransactionID() {
        return transactionID;
    }

    public void setTransactionID(String transactionID) {
        this.transactionID = transactionID;
    }

    public String getToken() {
        return token;
    }

    public void setToken(String token) {
        this.token = token;
    }

    public String getOwner() {
        return owner;
    }

    public void setOwner(String owner) {
        this.owner = owner;
    }

    private void sleep(int millis) {
        try {
            Thread.sleep(millis);
        } catch (InterruptedException e) {
            e.printStackTrace();
        }
    }

    @Override
    public void run() {
        //CHECK REQUEST FOR COMPLETE

        this.sleep(5000);
    }
}
