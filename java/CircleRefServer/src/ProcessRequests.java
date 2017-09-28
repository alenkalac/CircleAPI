import java.util.ArrayList;
import java.util.List;

public class ProcessRequests implements Runnable {

    private List<Request> requests;

    public ProcessRequests() {
        System.out.println("STARTING PROCESS REQUESTS SERVICE  ");
        requests = new ArrayList<>();
    }

    public void addRequest(Request request) {
        requests.add(request);
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
            for(Request r : requests) {
                if(!r.isAlive())
                    r.start();
                requests.remove(r);
            }
            this.sleep(5000);
        }
    }
}
