public class RequestFetcher implements Runnable {

    private int lastID;
    private ProcessRequests pRequsts;

    public RequestFetcher(ProcessRequests pRequsts) {
        System.out.println("STARTING FETCH SERVICE");
        this.pRequsts = pRequsts;
        lastID = 0; //API CALL TO GET THE LAST PROCESSED ID.
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
        while(true) {

            this.sleep(5000);
        }
    }
}
